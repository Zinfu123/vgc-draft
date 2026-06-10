<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { isReverbBroadcastClientConfigured } from '@/lib/broadcasting';
import { useForm } from '@inertiajs/vue3';
import { useEchoPublic } from '@laravel/echo-vue';
import { nextTick, onMounted, ref } from 'vue';

interface Message {
    id: number;
    set_id: number;
    user_id: number;
    user_name: string;
    body: string;
    created_at: string;
}

interface Props {
    setId: number;
    initialMessages: Message[];
    currentUserId: number;
}

const props = defineProps<Props>();

const messages = ref<Message[]>([...props.initialMessages]);
const messageListRef = ref<HTMLElement | null>(null);

const messageForm = useForm({ body: '' });

function scrollToBottom(): void {
    nextTick(() => {
        if (messageListRef.value) {
            messageListRef.value.scrollTop = messageListRef.value.scrollHeight;
        }
    });
}

onMounted(() => {
    scrollToBottom();
});

function sendMessage(): void {
    messageForm.post(route('sets.messages.store', { set: props.setId }), {
        preserveScroll: true,
        onSuccess: () => {
            messageForm.reset('body');
        },
    });
}

function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

const isCurrentUser = (userId: number): boolean => userId === props.currentUserId;

if (isReverbBroadcastClientConfigured) {
    useEchoPublic<Message>(`match.chat.${props.setId}`, 'MatchMessageSentEvent', (event) => {
        messages.value.push(event);
        scrollToBottom();
    });
}
</script>

<template>
    <div class="flex h-full flex-col">
        <div ref="messageListRef" class="flex flex-1 flex-col gap-3 overflow-y-auto px-4 py-4">
            <p v-if="messages.length === 0" class="text-muted-foreground py-8 text-center text-sm">
                No messages yet. Say hello to your opponent!
            </p>
            <div
                v-for="msg in messages"
                :key="msg.id"
                class="flex gap-2"
                :class="isCurrentUser(msg.user_id) ? 'flex-row-reverse' : 'flex-row'"
            >
                <div
                    class="max-w-[75%] rounded-lg px-3 py-2 text-sm shadow-sm"
                    :class="
                        isCurrentUser(msg.user_id)
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-foreground'
                    "
                >
                    <p v-if="!isCurrentUser(msg.user_id)" class="mb-0.5 text-xs font-medium opacity-70">
                        {{ msg.user_name }}
                    </p>
                    <p class="break-words">{{ msg.body }}</p>
                    <p class="mt-0.5 text-right text-xs opacity-60">{{ formatDateTime(msg.created_at) }}</p>
                </div>
            </div>
        </div>

        <div class="border-border border-t px-4 py-3 shrink-0">
            <form class="flex gap-2" @submit.prevent="sendMessage">
                <input
                    v-model="messageForm.body"
                    type="text"
                    placeholder="Type a message…"
                    maxlength="1000"
                    class="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring min-h-9 flex-1 rounded-md border px-3 py-2 text-sm focus:ring-2 focus:outline-none"
                />
                <Button type="submit" size="sm" :disabled="messageForm.processing || !messageForm.body.trim()">
                    Send
                </Button>
            </form>
            <p v-if="messageForm.errors.body" class="text-destructive mt-1 text-xs">{{ messageForm.errors.body }}</p>
        </div>
    </div>
</template>
