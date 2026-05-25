<script setup>
import Modal from "@/Components/App/Modal.vue";

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    previewSrc: {
        type: String,
        default: "",
    },
    fileName: {
        type: String,
        default: "Image preview",
    },
});

const emit = defineEmits(["update:modelValue"]);

const close = () => {
    emit("update:modelValue", false);
};
</script>

<template>
    <Modal :show="modelValue" max-width="2xl" @close="close">
        <div class="bg-gray-900 p-3 sm:p-4">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="truncate pr-3 text-sm font-semibold text-white sm:text-base">
                    {{ fileName }}
                </h3>
                <button
                    type="button"
                    class="rounded bg-white/10 px-2 py-1 text-xs font-medium text-white hover:bg-white/20"
                    @click="close"
                >
                    Close
                </button>
            </div>

            <div class="flex max-h-[75vh] items-center justify-center overflow-hidden rounded bg-black/30 p-2">
                <img
                    v-if="previewSrc"
                    :src="previewSrc"
                    :alt="fileName"
                    class="max-h-[70vh] w-auto max-w-full rounded object-contain"
                />

                <div v-else class="py-10 text-sm text-white/80">
                    Preview tidak tersedia untuk file ini.
                </div>
            </div>
        </div>
    </Modal>
</template>