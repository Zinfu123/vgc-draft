<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Mail, Lock, Eye, EyeOff } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
    processing: boolean;
    errors: {
        email?: string;
        password?: string;
    };
}

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    form: LoginForm;
    onSubmit: () => void;
}>();

const showPassword = ref(false);

// Computed properties to avoid mutating props directly
// Inertia forms are designed to be mutated, so we disable the eslint rule
const email = computed({
    get: () => props.form.email,
    set: (value: string) => {
        // eslint-disable-next-line vue/no-mutating-props
        props.form.email = value;
    },
});

const password = computed({
    get: () => props.form.password,
    set: (value: string) => {
        // eslint-disable-next-line vue/no-mutating-props
        props.form.password = value;
    },
});

const remember = computed({
    get: () => props.form.remember,
    set: (value: boolean) => {
        // eslint-disable-next-line vue/no-mutating-props
        props.form.remember = value;
    },
});
</script>

<template>
    <div class="relative z-10 w-full max-w-xl px-6">
        <div class="relative">
            <!-- Pokeball container -->
            <div class="bg-gray-800 rounded-full aspect-square shadow-xl border-4 overflow-hidden relative border-black max-w-xl mx-auto">
                <!-- Top half - Blue with shading -->
                <div class="absolute top-0 left-0 right-0 h-1/2 bg-gradient-to-b from-blue-700 to-blue-800">
                    <!-- Radial highlight for spherical effect -->
                    <div
                        class="absolute inset-0 opacity-30"
                        style="background: radial-gradient(circle at 35% 30%, rgba(255, 255, 255, 0.8) 0%, transparent 50%)"
                    ></div>
                    <!-- Edge shadow -->
                    <div
                        class="absolute inset-0 opacity-40"
                        style="background: radial-gradient(circle at 50% 50%, transparent 40%, rgba(0, 0, 0, 0.5) 100%)"
                    ></div>
                </div>
                
                <!-- Bottom half - Light Blue with shading -->
                <div class="absolute bottom-0 left-0 right-0 h-1/2 bg-gradient-to-b from-blue-100 to-blue-200">
                    <!-- Subtle highlight -->
                    <div
                        class="absolute inset-0 opacity-20"
                        style="background: radial-gradient(circle at 35% 70%, rgba(255, 255, 255, 0.9) 0%, transparent 50%)"
                    ></div>
                    <!-- Edge shadow -->
                    <div
                        class="absolute inset-0 opacity-30"
                        style="background: radial-gradient(circle at 50% 50%, transparent 40%, rgba(0, 0, 0, 0.4) 100%)"
                    ></div>
                </div>
                
                <!-- Middle black band -->
                <div class="absolute top-1/2 left-0 right-0 h-12 -mt-6 bg-blue-950 border-t-4 border-b-4 border-black">
                    <!-- Band shadow -->
                    <div
                        class="absolute inset-0 opacity-50"
                        style="background: linear-gradient(to bottom, rgba(0, 0, 0, 0.5) 0%, transparent 30%, transparent 70%, rgba(0, 0, 0, 0.5) 100%)"
                    ></div>
                </div>
                
                <!-- Form content - Top section -->
                <div class="absolute top-0 left-0 right-0 h-1/2 z-10 flex items-center justify-center px-8 pb-12">
                    <div class="text-center">
                        <!-- Pokemon Gym Badge Logo -->
                        <div
                            class="inline-block relative"
                            style="transform: perspective(400px) rotateX(10deg); transform-style: preserve-3d;"
                        >
                            <!-- Outer octagonal badge frame with metallic effect -->
                            <div
                                class="w-40 h-40 relative"
                                style="clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%);"
                            >
                                <!-- Black outline -->
                                <div
                                    class="absolute inset-0"
                                    style="background: #000; clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%);"
                                ></div>

                                <!-- Metallic outer ring -->
                                <div
                                    class="absolute inset-1"
                                    style="background: linear-gradient(135deg, #60a5fa 0%, #1e3a8a 50%, #3b82f6 100%); box-shadow: 0 8px 16px rgba(0, 0, 0, 0.5), inset 0 2px 4px rgba(255, 255, 255, 0.3), inset 0 -2px 4px rgba(0, 0, 0, 0.3); clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%);"
                                >
                                    <!-- Metallic shine band - diagonal -->
                                    <div
                                        class="absolute inset-0"
                                        style="background: linear-gradient(120deg, transparent 0%, transparent 25%, rgba(255, 255, 255, 0.1) 35%, rgba(255, 255, 255, 0.3) 42%, rgba(255, 255, 255, 0.5) 48%, rgba(255, 255, 255, 0.6) 50%, rgba(255, 255, 255, 0.5) 52%, rgba(255, 255, 255, 0.3) 58%, rgba(255, 255, 255, 0.1) 65%, transparent 75%, transparent 100%); clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%);"
                                    ></div>
                                    <!-- Top-left highlight for beveled effect -->
                                    <div
                                        class="absolute inset-0"
                                        style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.4) 0%, transparent 40%); clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%);"
                                    ></div>
                                    <!-- Bottom-right shadow for beveled effect -->
                                    <div
                                        class="absolute inset-0"
                                        style="background: linear-gradient(-45deg, rgba(0, 0, 0, 0.4) 0%, transparent 40%); clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%);"
                                    ></div>
                                </div>

                                <!-- Inner octagonal layer -->
                                <div
                                    class="absolute inset-3"
                                    style="clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%); background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 50%, #1e40af 100%); box-shadow: inset 0 2px 6px rgba(0, 0, 0, 0.6), inset 0 -1px 3px rgba(255, 255, 255, 0.2);"
                                >
                                    <!-- Metallic shine on inner ring -->
                                    <div
                                        class="absolute inset-0"
                                        style="background: linear-gradient(120deg, transparent 0%, transparent 30%, rgba(255, 255, 255, 0.08) 40%, rgba(255, 255, 255, 0.15) 45%, rgba(255, 255, 255, 0.25) 48%, rgba(255, 255, 255, 0.3) 50%, rgba(255, 255, 255, 0.25) 52%, rgba(255, 255, 255, 0.15) 55%, rgba(255, 255, 255, 0.08) 60%, transparent 70%, transparent 100%); clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%);"
                                    ></div>
                                </div>

                                <!-- Center badge area -->
                                <div
                                    class="absolute inset-6"
                                    style="clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%); background: linear-gradient(to bottom, #3b82f6 0%, #2563eb 100%); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4), inset 0 1px 2px rgba(255, 255, 255, 0.3);"
                                >
                                    <!-- Radial highlight for depth -->
                                    <div
                                        class="absolute inset-0"
                                        style="background: radial-gradient(circle at 40% 30%, rgba(255, 255, 255, 0.3) 0%, transparent 60%); clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%);"
                                    ></div>
                                    
                                    <!-- Metallic shine on center -->
                                    <div
                                        class="absolute inset-0"
                                        style="background: linear-gradient(115deg, transparent 0%, transparent 20%, rgba(255, 255, 255, 0.1) 32%, rgba(255, 255, 255, 0.2) 40%, rgba(255, 255, 255, 0.35) 46%, rgba(255, 255, 255, 0.45) 50%, rgba(255, 255, 255, 0.35) 54%, rgba(255, 255, 255, 0.2) 60%, rgba(255, 255, 255, 0.1) 68%, transparent 80%, transparent 100%); clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%);"
                                    ></div>

                                    <!-- VGC Text - embossed -->
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <h1
                                            class="text-5xl font-bold"
                                            style="color: #93c5fd; text-shadow: 0 -2px 0 rgba(0, 0, 0, 0.5), 0 2px 2px rgba(255, 255, 255, 0.4), 0 4px 6px rgba(0, 0, 0, 0.3); -webkit-text-stroke: 1px rgba(30, 64, 175, 0.5); letter-spacing: 0.05em;"
                                        >
                                            VGC
                                        </h1>
                                    </div>
                                </div>

                                <!-- Decorative corner accents -->
                                <div
                                    v-for="(rotation, i) in [0, 90, 180, 270]"
                                    :key="i"
                                    class="absolute w-2 h-2 bg-blue-300 rounded-full"
                                    :style="`top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(${rotation}deg) translateY(-68px); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.4), inset 0 1px 1px rgba(255, 255, 255, 0.6);`"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Form content - Bottom section -->
                <div class="absolute bottom-0 left-0 right-0 h-1/2 z-10 px-8 pb-8 pt-20 flex flex-col items-center">
                    <div v-if="status" class="mb-2 text-center text-sm font-medium text-green-600">
                        {{ status }}
                    </div>
                    <form id="login-form" @submit.prevent="onSubmit" class="space-y-3 w-full max-w-xs">
                        <div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                    <Mail class="h-4 w-4 text-gray-600" />
                                </div>
                                <input
                                    id="email"
                                    type="email"
                                    v-model="email"
                                    required
                                    autofocus
                                    :tabindex="1"
                                    autocomplete="email"
                                    class="block w-full pl-9 pr-2.5 py-2 text-sm bg-white/90 backdrop-blur border border-gray-300 text-gray-900 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all placeholder-gray-500"
                                    placeholder="you@example.com"
                                />
                            </div>
                            <InputError :message="form.errors.email" />
                        </div>

                        <div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                    <Lock class="h-4 w-4 text-gray-600" />
                                </div>
                                <input
                                    id="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    v-model="password"
                                    required
                                    :tabindex="2"
                                    autocomplete="current-password"
                                    class="block w-full pl-9 pr-9 py-2 text-sm bg-white/90 backdrop-blur border border-gray-300 text-gray-900 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all placeholder-gray-500"
                                    placeholder="Enter your password"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-2.5 flex items-center"
                                    tabindex="-1"
                                >
                                    <EyeOff v-if="showPassword" class="h-4 w-4 text-gray-600 hover:text-gray-800" />
                                    <Eye v-else class="h-4 w-4 text-gray-600 hover:text-gray-800" />
                                </button>
                            </div>
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between text-xs pt-1 pl-2">
                            <label class="flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="remember"
                                    :tabindex="3"
                                    class="w-3.5 h-3.5 text-blue-500 bg-white border-gray-300 rounded focus:ring-2 focus:ring-blue-500 accent-blue-500"
                                />
                                <span class="ml-2 text-gray-800 drop-shadow">Remember me</span>
                            </label>
                            <TextLink
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-blue-600 hover:text-blue-700 drop-shadow transition-colors"
                                :tabindex="5"
                            >
                                Forgot password?
                            </TextLink>
                        </div>
                    </form>

                    <div class="mt-3 text-center">
                        <p class="text-gray-800 drop-shadow text-xs">
                            Don't have an account?
                            <TextLink :href="route('register')" class="text-blue-600 hover:text-blue-700 transition-colors" :tabindex="5">
                                Sign up
                            </TextLink>
                        </p>
                    </div>
                </div>
                
                <!-- Center circle button - Sign In -->
                <button
                    type="submit"
                    form="login-form"
                    :disabled="form.processing"
                    class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-20 group disabled:opacity-50 disabled:cursor-not-allowed"
                    :tabindex="4"
                >
                    <div class="w-36 h-36 rounded-full bg-gray-800 border-4 border-black flex items-center justify-center shadow-2xl transition-transform group-hover:scale-110 group-active:scale-95">
                        <div class="w-20 h-20 rounded-full bg-white group-hover:bg-blue-400 border-4 border-blue-500 group-hover:border-blue-600 flex items-center justify-center transition-colors">
                            <span v-if="form.processing" class="text-blue-600 group-hover:text-white text-3xl font-bold transition-colors">...</span>
                            <span v-else class="text-blue-600 group-hover:text-white text-3xl font-bold transition-colors">GO</span>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>

