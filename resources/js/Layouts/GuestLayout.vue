<script setup>
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import { Link } from "@inertiajs/vue3";
</script>

<template>
    <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-0 bg-gray-100">
        <div class="w-full max-w-3xl px-4 sm:px-6">
            <div class="flex items-center justify-center gap-2 py-4">
                <Link href="/" class="flex items-center">
                    <ApplicationLogo class="h-10 sm:h-16" />
                </Link>

                <div class="text-center">
                    <span class="text-2xl sm:text-4xl font-bold text-blue-600">
                        GDStore
                    </span>
                </div>
            </div>

            <div class="w-full sm:max-w-md lg:max-w-lg mx-auto mt-4 sm:mt-6 px-4 sm:px-6 py-4 bg-white shadow-md overflow-hidden rounded-lg">
                <slot />
            </div>

            <div class="mx-auto mt-4 sm:mt-6 text-center">
                <slot name="registerLink"></slot>
            </div>
        </div>
    </div>
</template>
