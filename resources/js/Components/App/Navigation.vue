<script setup>
import { Link } from "@inertiajs/vue3";
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import CreateNewDropdown from "@/Components/App/CreateNewDropdown.vue";
import NavLink from "@/Components/App/NavLink.vue";
import { XMarkIcon } from "@heroicons/vue/24/outline";

defineProps({
    mobileOpen: {
        type: Boolean,
        default: false,
    },
});

defineEmits(["close"]);
</script>

<template>
    <nav
        class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full bg-white shadow-lg transition-transform duration-200 md:static md:z-auto md:w-auto md:min-w-[200px] md:translate-x-0 md:shadow-none"
        :class="mobileOpen ? 'translate-x-0' : ''"
    >
        <div class="flex items-center justify-between px-3 py-4 md:py-0 md:h-[80px]">
            <Link :href="route('myFiles')" class="flex items-center">
                <ApplicationLogo class="h-7" />
                <span class="mx-0.5 text-2xl font-bold text-blue-600">
                    GDStore
                </span>
                <ApplicationLogo class="h-7" />
            </Link>

            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md border border-gray-200 p-2 text-gray-700 md:hidden"
                @click="$emit('close')"
                aria-label="Close sidebar"
            >
                <XMarkIcon class="h-5 w-5" />
            </button>
        </div>

        <div class="px-3">
            <CreateNewDropdown />

            <div class="py-3">
                <NavLink
                    :href="route('myFiles')"
                    :active="$page.props.current_route === 'myFiles'"
                    >My Files</NavLink
                >
                <NavLink
                    :href="route('sharedWithMe')"
                    :active="$page.props.current_route === 'sharedWithMe'"
                    >Shared With Me</NavLink
                >
                <NavLink
                    :href="route('sharedByMe')"
                    :active="$page.props.current_route === 'sharedByMe'"
                    >Shared By Me</NavLink
                >
                <NavLink
                    :href="route('trash')"
                    :active="$page.props.current_route === 'trash'"
                    >Trash</NavLink
                >
            </div>
        </div>
    </nav>
</template>
