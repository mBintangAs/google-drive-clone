<script setup>
import { ref, onMounted, watch } from "vue";
import Navigation from "@/Components/App/Navigation.vue";
import SearchForm from "@/Components/App/SearchForm.vue";
import UserSettingsDropDown from "@/Components/App/UserSettingsDropDown.vue";
import {
    FILE_UPLOAD_STARTED,
    emitter,
    showErrorDialog,
    showSuccessNotification,
} from "@/event-bus";
import { useForm, usePage } from "@inertiajs/vue3";
import FormProgress from "@/Components/App/FormProgress.vue";
import ErrorDialog from "@/Components/ErrorDialog.vue";
import Notification from "@/Components/Notification.vue";
import { Bars3Icon, XMarkIcon } from "@heroicons/vue/24/outline";

const page = usePage();
const dragOver = ref(false);
const sidebarOpen = ref(false);

onMounted(() => {
    emitter.on(FILE_UPLOAD_STARTED, uploadFiles);
});

watch(
    () => page.url,
    () => {
        sidebarOpen.value = false;
    }
);

const openSidebar = () => {
    sidebarOpen.value = true;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};

const fileUploadForm = useForm({
    files: [],
    relative_paths: [],
    parent_id: null,
});

const onDrop = (e) => {
    dragOver.value = false;
    const files = e.dataTransfer.files;
    if (!files.length) {
        return;
    }

    uploadFiles(files);
};
const onDragOver = () => {
    dragOver.value = true;
};
const onDragLeave = () => {
    dragOver.value = false;
};

const uploadFiles = (files) => {
    fileUploadForm.parent_id = page.props.rootFolder.id;
    fileUploadForm.files = files;
    fileUploadForm.relative_paths = [...files].map(
        (file) => file.webkitRelativePath
    );

    fileUploadForm.post(route("files.store"), {
        onSuccess: () => {
            showSuccessNotification(
                `${files.length} files have been uploaded.`
            );
        },
        onError: (errors) => {
            let message = "";

            if (Object.keys(errors).length > 0) {
                message = errors[Object.keys(errors)[0]];
            } else {
                message = "Error during file upload. Try again after sometime.";
            }

            showErrorDialog(message);
        },
        onFinish: () => {
            fileUploadForm.clearErrors();
            fileUploadForm.reset();
        },
    });
};
</script>

<template>
    <div class="h-screen bg-gray-50 flex w-full gap-4">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-black/40 md:hidden"
            @click="closeSidebar"
        ></div>

        <Navigation :mobile-open="sidebarOpen" @close="closeSidebar" />

        <main
            class="flex flex-col flex-1 px-4 overflow-hidden min-w-0"
            :class="dragOver ? 'dropzone' : ''"
            @drop.prevent="onDrop"
            @dragover.prevent="onDragOver"
            @dragleave.prevent="onDragLeave"
        >
            <template
                v-if="dragOver"
                class="text-gray-500 text-sm text-center py-8"
            >
                Drag files here to upload.
            </template>

            <template v-else>
                    <div class="flex items-center justify-between w-full gap-3 py-3 md:py-0">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white p-2 text-gray-700 shadow-sm md:hidden"
                            @click="openSidebar"
                            aria-label="Open sidebar"
                        >
                            <Bars3Icon class="h-5 w-5" />
                        </button>

                    <SearchForm />
                    <UserSettingsDropDown />
                </div>

                <div class="flex flex-col flex-1 overflow-hidden">
                    <slot></slot>
                </div>
            </template>
        </main>
    </div>

    <FormProgress :form="fileUploadForm" />
    <ErrorDialog />
    <Notification />
</template>

<style scoped>
.dropzone {
    width: 100%;
    height: 100%;
    color: #8d8d8d;
    border: 2px dashed gray;
    display: flex;
    justify-content: center;
    align-items: center;
}
</style>
