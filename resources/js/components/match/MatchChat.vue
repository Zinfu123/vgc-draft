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

interface ScheduleRequest {
    id: number;
    set_id: number;
    proposed_by_user_id: number;
    proposed_by_user_name: string;
    proposed_at: string;
    status: 'pending' | 'accepted' | 'declined';
}

interface Props {
    setId: number;
    initialMessages: Message[];
    initialScheduleRequest: ScheduleRequest | null;
    currentUserId: number;
}

const props = defineProps<Props>();

const messages = ref<Message[]>([...props.initialMessages]);
const scheduleRequest = ref<ScheduleRequest | null>(props.initialScheduleRequest);
const messageListRef = ref<HTMLElement | null>(null);

const messageForm = useForm({ body: '' });
const respondForm = useForm({ status: '' as 'accepted' | 'declined' });

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

function respondToRequest(status: 'accepted' | 'declined'): void {
    if (!scheduleRequest.value) {
        return;
    }
    respondForm.status = status;
    respondForm.patch(
        route('sets.schedule-requests.update', {
            set: props.setId,
            scheduleRequest: scheduleRequest.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                respondForm.reset('status');
            },
        },
    );
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
const isProposer = (request: ScheduleRequest): boolean => request.proposed_by_user_id === props.currentUserId;

if (isReverbBroadcastClientConfigured) {
    useEchoPublic<Message>(`match.chat.${props.setId}`, 'MatchMessageSentEvent', (event) => {
        messages.value.push(event);
        scrollToBottom();
    });

    useEchoPublic<ScheduleRequest>(`match.chat.${props.setId}`, 'MatchScheduleRequestUpdatedEvent', (event) => {
        scheduleRequest.value = event;
    });
}
</script>

<template>
    <div class="flex h-full flex-col">
        <div
            v-if="scheduleRequest && scheduleRequest.status === 'pending'"
            class="border-border bg-muted/40 border-b px-4 py-3 shrink-0"
        >
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-foreground">
                        <span v-if="isProposer(scheduleRequest)">You proposed</span>
                        <span v-else>{{ scheduleRequest.proposed_by_user_name }} proposed</span>
                        a time:
                        <span class="font-semibold">{{ formatDateTime(scheduleRequest.proposed_at) }}</span>
                    </p>
                    <p v-if="isProposer(scheduleRequest)" class="text-muted-foreground mt-0.5 text-xs">
                        Waiting for your opponent to respond.
                    </p>
                </div>
                <div v-if="!isProposer(scheduleRequest)" class="flex shrink-0 gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        class="border-destructive/50 text-destructive hover:bg-destructive/10 hover:text-destructive"
                        :disabled="respondForm.processing"
                        @click="respondToRequest('declined')"
                    >
                        Decline
                    </Button>
                    <Button size="sm" :disabled="respondForm.processing" @click="respondToRequest('accepted')">
                        Accept
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-else-if="scheduleRequest && scheduleRequest.status === 'accepted'"
            class="border-border bg-green-50/60 dark:bg-green-950/20 border-b px-4 py-3 shrink-0"
        >
            <p class="text-sm font-medium text-green-700 dark:text-green-400">
                Match scheduled for
                <span class="font-semibold">{{ formatDateTime(scheduleRequest.proposed_at) }}</span>
            </p>
        </div>

        <div ref="messageListRef" class="flex flex-1 flex-col gap-3 overflow-y-auto px-4 py-4">
            <p v-if="messages.length === 0" class="text-muted-foreground py-8 text-center text-sm">
                No messages yet. Start the conversation to schedule your match.
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
