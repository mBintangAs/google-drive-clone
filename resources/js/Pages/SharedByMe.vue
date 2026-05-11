<script setup>
import { Head, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import FileIcon from "@/Components/App/FileIcon.vue";
import ImagePreviewModal from "@/Components/App/ImagePreviewModal.vue";
import { ref, onMounted, onUpdated, computed } from "vue";
import { httpGet } from "@/Helper/http-helper";
import Checkbox from "@/Components/Checkbox.vue";
import DownloadFileButton from "@/Components/App/DownloadFileButton.vue";

const props = defineProps({
    files: Object,
    folder: Object,
    ancestors: Object,
});

const allFiles = ref({
    data: props.files.data,
    next: props.files.links.next,
});
const showImagePreviewModal = ref(false);
const previewFile = ref(null);

const openFolder = (file) => {
    if (!file.is_folder) {
        return;
    }

    router.visit(route('sharedByMe', { folder: file.path }));
};

const isPreviewableImage = (file) => {
    return !file.is_folder && typeof file.mime === "string" && file.mime.startsWith("image/");
};

const previewImage = (file) => {
    if (!isPreviewableImage(file)) {
        return;
    }

    previewFile.value = file;
    showImagePreviewModal.value = true;
};

const closeImagePreview = () => {
    showImagePreviewModal.value = false;
};

const allSelected = ref(false);
const selected = ref({});

const selectedIds = computed(() => {
    return Object.entries(selected.value)
        .filter((elem) => elem[1])
        .map((elem) => elem[0]);
});

const loadMore = () => {
    if (allFiles.value.next === null) {
        return;
    }

    httpGet(allFiles.value.next).then((res) => {
        allFiles.value.data = [...allFiles.value.data, ...res.data];
        allFiles.value.next = res.links.next;
    });
};

const onSelectAllChange = () => {
    allFiles.value.data.forEach((f) => {
        selected.value[f.id] = allSelected.value;
    });
};

const toggleFileSelect = (file) => {
    selected.value[file.id] = !selected.value[file.id];
    onSelectCheckboxChange(file);
};

const onSelectCheckboxChange = (file) => {
    if (!selected.value[file.id]) {
        allSelected.value = false;
    } else {
        let checked = true;

        for (let file of allFiles.value.data) {
            if (!selected.value[file.id]) {
                checked = false;
                break;
            }
        }

        allSelected.value = checked;
    }
};

const resetForm = () => {
    allSelected.value = false;
    selected.value = {};
};

onUpdated(() => {
    allFiles.value = {
        data: props.files.data,
        next: props.files.links.next,
    };
});

const loadMoreIntersect = ref(null);
onMounted(() => {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => entry.isIntersecting && loadMore());
        },
        {
            rootMargin: "-250px 0px 0px 0px",
        }
    );

    observer.observe(loadMoreIntersect.value);
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Shared By Me" />

        <nav class="flex items-center justify-between p-1 mb-3">
            <ol class="inline-flex items-center space-x-1">
                <li class="inline-flex items-center">
                    <Link
                        :href="route('sharedByMe')"
                        class="flex items-center font-medium text-gray-700 hover:text-blue-600"
                    >
                        Shared By Me
                    </Link>
                </li>
            </ol>

            <div class="space-x-4">
                <DownloadFileButton
                    :all="allSelected"
                    :ids="selectedIds"
                    :shared-by-me="true"
                    class="mr-2"
                />
            </div>
        </nav>

        <div class="flex-1 overflow-auto">
            <table
                class="w-full text-sm text-left text-gray-500 rounded overflow-hidden shadow"
            >
                <thead
                    class="text-xs text-gray-700 uppercase tracking-wider bg-gray-200"
                >
                    <tr>
                        <th class="px-6 py-3">
                            <Checkbox
                                v-model:checked="allSelected"
                                @change="onSelectAllChange"
                            />
                        </th>
                        <th class="pl-6 pr-0 py-3">Name</th>
                        <th class="px-6 py-3">Path</th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        class="border-b hover:bg-blue-100 cursor-pointer transition ease-in-out duration-200"
                        :class="
                            selected[file.id] || allSelected
                                ? 'bg-blue-50'
                                : 'bg-white'
                        "
                        v-for="file in allFiles.data"
                        :key="file.id"
                        @dblclick="isPreviewableImage(file) ? previewImage(file) : openFolder(file)"
                        @click="($event) => toggleFileSelect(file)"
                    >
                        <td
                            class="pl-6 py-4 pr-0 w-7 max-w-7 font-medium tracking-wider text-gray-900 whitespace-nowrap"
                        >
                            <Checkbox
                                v-model="selected[file.id]"
                                :checked="selected[file.id] || allSelected"
                                @change="
                                    ($event) => onSelectCheckboxChange(file)
                                "
                            />
                        </td>
                        <td
                            class="px-6 py-4 font-medium tracking-wider text-gray-900 whitespace-nowrap"
                        >
                            <div class="flex items-center">
                                <button
                                    v-if="isPreviewableImage(file)"
                                    type="button"
                                    class="mr-2 h-8 w-8 overflow-hidden rounded border border-gray-300"
                                    @click.stop.prevent="previewImage(file)"
                                >
                                    <img
                                        :src="route('files.preview', { file: file.id })"
                                        :alt="file.name"
                                        class="h-full w-full object-cover"
                                    />
                                </button>
                                <FileIcon v-else :file="file" />
                                <div class="ml-2">
                                    <span v-if="file.is_folder">
                                        <a @click.stop.prevent="openFolder(file)" class="font-medium text-blue-600 hover:underline">{{ file.name }}</a>
                                    </span>
                                    <span v-else>
                                        {{ file.name }}
                                    </span>
                                    <button
                                        v-if="isPreviewableImage(file)"
                                        type="button"
                                        @click.stop.prevent="previewImage(file)"
                                        class="ml-2 text-xs font-medium text-blue-600 hover:underline"
                                    >
                                        Preview
                                    </button>
                                </div>
                            </div>
                        </td>
                        <td
                            class="px-6 py-4 font-medium tracking-wider text-gray-900 whitespace-nowrap"
                        >
                            {{ file.path }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                v-if="!allFiles.data.length"
                class="text-center tracking-wide py-3 text-gray-700 bg-white shadow rounded-b"
            >
                No files or folders available in this directory.
            </div>

            <div ref="loadMoreIntersect"></div>
        </div>

        <ImagePreviewModal
            v-model="showImagePreviewModal"
            :image-src="previewFile ? route('files.preview', { file: previewFile.id }) : ''"
            :file-name="previewFile?.name || 'Image preview'"
            @update:modelValue="closeImagePreview"
        />
    </AuthenticatedLayout>
</template>
