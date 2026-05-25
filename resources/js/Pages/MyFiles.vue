<script setup>
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import {
    ChevronRightIcon,
    HomeIcon,
    StarIcon as StarSolidIcon,
    EllipsisVerticalIcon,
} from "@heroicons/vue/20/solid";
import FileIcon from "@/Components/App/FileIcon.vue";
import { ref, onMounted, onUpdated, onUnmounted, computed } from "vue";
import { httpGet, httpPost } from "@/Helper/http-helper";
import Checkbox from "@/Components/Checkbox.vue";
import DeleteFileButton from "@/Components/App/DeleteFileButton.vue";
import DownloadFileButton from "@/Components/App/DownloadFileButton.vue";
import { StarIcon as StarOutlineIcon } from "@heroicons/vue/24/outline";
import { ON_SEARCH, emitter, showSuccessNotification, showErrorNotification } from "@/event-bus";
import ShareFileButton from "@/Components/App/ShareFileButton.vue";
import MoveFileModal from "@/Components/App/MoveFileModal.vue";
import ShareFilesModal from "@/Components/App/ShareFilesModal.vue";
import ConfirmationDialog from "@/Components/App/ConfirmationDialog.vue";
import ImagePreviewModal from "@/Components/App/ImagePreviewModal.vue";

const props = defineProps({
    files: Object,
    folder: Object,
    ancestors: Object,
});

const allFiles = ref({
    data: props.files.data,
    next: props.files.links.next,
});

const allSelected = ref(false);
const selected = ref({});
const onlyFavourites = ref(false);
const openDropdown = ref(null);
const renamingFile = ref(null);
const newFileName = ref('');
const showMoveModal = ref(false);
const fileToMove = ref(null);
const showShareModal = ref(false);
const showMobileActions = ref(false);
const showDeleteConfirm = ref(false);
const mobileSelectionMode = ref(false);
const longPressTimer = ref(null);
const suppressNextTap = ref(false);
const showImagePreviewModal = ref(false);
const previewFile = ref(null);

const selectedIds = computed(() => {
    return Object.entries(selected.value)
        .filter((elem) => elem[1])
        .map((elem) => elem[0]);
});

const openFolder = (file) => {
    if (!file.is_folder) {
        return;
    }

    router.visit(route("myFiles", { folder: file.path }));
};

const isPreviewableImage = (file) => {
    return !file.is_folder && typeof file.mime === "string" && file.mime.startsWith("image/");
};

const isPreviewableFile = (file) => {
    return isPreviewableImage(file) || file.mime === "application/pdf" || file.mime === "application/x-pdf";
};

const previewFileAction = (file) => {
    if (!isPreviewableFile(file)) {
        return;
    }

    if (file.mime === "application/pdf" || file.mime === "application/x-pdf") {
        window.open(route("files.preview", { file: file.id }), "_blank", "noopener,noreferrer");
        closeDropdown();
        return;
    }

    previewFile.value = file;
    showImagePreviewModal.value = true;
    closeDropdown();
};

const closeImagePreview = () => {
    showImagePreviewModal.value = false;
};

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
    mobileSelectionMode.value = true;
    allFiles.value.data.forEach((f) => {
        selected.value[f.id] = allSelected.value;
    });

    if (!allSelected.value) {
        mobileSelectionMode.value = false;
    }
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

const onDelete = () => {
    allSelected.value = false;
    selected.value = {};
    mobileSelectionMode.value = false;
};

const startLongPress = (file) => {
    if (longPressTimer.value) {
        clearTimeout(longPressTimer.value);
    }

    longPressTimer.value = setTimeout(() => {
        mobileSelectionMode.value = true;
        suppressNextTap.value = true;

        if (!selected.value[file.id]) {
            selected.value[file.id] = true;
            onSelectCheckboxChange(file);
        }
    }, 450);
};

const cancelLongPress = () => {
    if (longPressTimer.value) {
        clearTimeout(longPressTimer.value);
        longPressTimer.value = null;
    }
};

const handleMobileCardTap = (file) => {
    if (suppressNextTap.value) {
        suppressNextTap.value = false;
        return;
    }

    if (mobileSelectionMode.value) {
        toggleFileSelect(file);

        if (!allSelected.value && selectedIds.value.length === 0) {
            mobileSelectionMode.value = false;
        }

        return;
    }

    if (isPreviewableFile(file)) {
        previewFileAction(file);
        return;
    }

    openFolder(file);
};

const hasSelectedFiles = computed(() => {
    return allSelected.value || selectedIds.value.length > 0;
});

const deleteFileForm = useForm({
    all: null,
    ids: [],
    parent_id: null,
});

const toggleMobileActions = () => {
    showMobileActions.value = !showMobileActions.value;
};

const closeMobileActions = () => {
    showMobileActions.value = false;
};

const openShareModal = () => {
    if (!hasSelectedFiles.value) {
        showErrorNotification("Please select at least one file or folder to share.");
        return;
    }

    showShareModal.value = true;
    closeMobileActions();
};

const closeShareModal = () => {
    showShareModal.value = false;
};

const downloadSelected = () => {
    if (!hasSelectedFiles.value) {
        showErrorNotification("Please select at least one file or folder to download.");
        return;
    }

    const urlParams = new URLSearchParams();

    if (page.props.rootFolder?.id) {
        urlParams.append("parent_id", page.props.rootFolder.id);
    }

    if (allSelected.value) {
        urlParams.append("all", "1");
    } else {
        for (let id of selectedIds.value) {
            urlParams.append("ids[]", id);
        }
    }

    window.location.href = `${route("files.download")}?${urlParams.toString()}`;
    closeMobileActions();
};

const openDeleteConfirm = () => {
    if (!hasSelectedFiles.value) {
        showErrorNotification("Please select at least one file or folder to delete.");
        return;
    }

    showDeleteConfirm.value = true;
    closeMobileActions();
};

const closeDeleteConfirm = () => {
    showDeleteConfirm.value = false;
};

const confirmDelete = () => {
    deleteFileForm.parent_id = page.props.rootFolder.id;
    if (allSelected.value) {
        deleteFileForm.all = true;
        deleteFileForm.ids = [];
    } else {
        deleteFileForm.ids = selectedIds.value;
    }

    deleteFileForm.delete(route("files.destroy"), {
        onSuccess: () => {
            showDeleteConfirm.value = false;
            showSuccessNotification("Selected files have been successfully deleted.");
            onDelete();
        },
    });
};

const toggleFavourite = (file) => {
    let actionType = "favourited";
    if (file.is_favourite) {
        actionType = "unfavourited";
    }

    httpPost(route("files.toggleFavourite"), { id: file.id }).then(() => {
        file.is_favourite = !file.is_favourite;
        showSuccessNotification(
            `The file has been successfully ${actionType}.`
        );
    });
};

const showOnlyFavourites = () => {
    const favourites = usePage().props.favourites;

    if (favourites === true) {
        return router.get(route("myFiles"));
    }

    return router.get(route("myFiles"), { favourites: 1 });
};

const goBack = () => {
    const ancestors = props.ancestors.data;
    
    if (ancestors.length > 1) {
        // Jika ada lebih dari 1 ancestor, navigasi ke parent (ancestor kedua terakhir)
        const parentFolder = ancestors[ancestors.length - 2];
        router.visit(route("myFiles", { folder: parentFolder.path }));
    } else if (ancestors.length === 1) {
        // Jika hanya ada 1 ancestor (root), navigasi ke my files root
        router.visit(route("myFiles"));
    }
    // Jika ancestors.length === 0, sudah di root, tidak melakukan apa-apa
};

const toggleDropdown = (fileId) => {
    openDropdown.value = openDropdown.value === fileId ? null : fileId;
};

const closeDropdown = () => {
    openDropdown.value = null;
};

const renameFile = (file) => {
    renamingFile.value = file.id;
    newFileName.value = file.name;
    closeDropdown();
    // Focus input setelah render
    setTimeout(() => {
        const input = document.querySelector(`#rename-input-${file.id}`);
        if (input) {
            input.focus();
            input.select();
        }
    }, 10);
};

const saveRename = (file) => {
    if (newFileName.value && newFileName.value !== file.name) {
        httpPost(route("files.rename"), { 
            id: file.id, 
            name: newFileName.value 
        }).then((response) => {
            file.name = newFileName.value; // Update local state
            showSuccessNotification(response.message || `File renamed to ${newFileName.value}`);
            cancelRename();
        }).catch((error) => {
            console.error('Rename error:', error);
            showErrorNotification('Failed to rename file');
            cancelRename();
        });
    } else {
        cancelRename();
    }
};

const cancelRename = () => {
    renamingFile.value = null;
    newFileName.value = '';
};

const handleRenameKeydown = (event, file) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        saveRename(file);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        cancelRename();
    }
};

const moveFile = (file) => {
    fileToMove.value = file;
    showMoveModal.value = true;
    closeDropdown();
};

const onFileMoved = (movedFile) => {
    // Remove file from current list as it's moved to another location
    const index = allFiles.value.data.findIndex(f => f.id === movedFile.id);
    if (index !== -1) {
        allFiles.value.data.splice(index, 1);
    }
    showMoveModal.value = false;
    fileToMove.value = null;
};

const onMoveModalClose = () => {
    showMoveModal.value = false;
    fileToMove.value = null;
};

const copyFile = (file) => {
    // TODO: Implementasi API call untuk copy
    console.log('Copy file:', file.name);
    showSuccessNotification(`File ${file.name} copied`);
    closeDropdown();
};

onUpdated(() => {
    allFiles.value = {
        data: props.files.data,
        next: props.files.links.next,
    };
});

const loadMoreIntersect = ref(null);
const page = usePage();
let search = ref("");
onMounted(() => {
    const favourites = page.props.favourites;
    onlyFavourites.value = favourites === true;
    search.value = page.props.search ?? "";
    emitter.on(ON_SEARCH, (value) => {
        search.value = value;
    });

    // Event listener untuk keyboard backspace
    const handleKeydown = (event) => {
        if (event.key === 'Backspace' && !event.target.matches('input, textarea')) {
            event.preventDefault();
            goBack();
        }
    };

    // Event listener untuk close dropdown saat click outside
    const handleClickOutside = (event) => {
        if (!event.target.closest('.dropdown-menu')) {
            closeDropdown();
        }
    };

    document.addEventListener('keydown', handleKeydown);
    document.addEventListener('click', handleClickOutside);

    // Cleanup event listener saat komponen di-unmount
    onUnmounted(() => {
        document.removeEventListener('keydown', handleKeydown);
        document.removeEventListener('click', handleClickOutside);
    });

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
        <Head title="My Files" />

        <nav class="flex items-center justify-between gap-2 p-1 mb-3 sm:gap-3">
            <ol class="inline-flex items-center space-x-1 min-w-0 overflow-x-auto flex-1 whitespace-nowrap">
                <li
                    v-for="ancestor in ancestors.data"
                    :key="ancestor.id"
                    class="inline-flex items-center"
                >
                    <Link
                        v-if="!ancestor.parent_id"
                        :href="route('myFiles')"
                        class="flex items-center font-medium text-gray-700 hover:text-blue-600"
                    >
                        <HomeIcon class="w-4 h-4 mr-1" />
                        My Files
                    </Link>

                    <div v-else class="flex items-center">
                        <ChevronRightIcon class="w-5 h-5" />
                        <Link
                            :href="route('myFiles', { folder: ancestor.path })"
                            class="font-medium text-gray-700 hover:text-blue-600"
                        >
                            {{ ancestor.name }}
                        </Link>
                    </div>
                </li>
            </ol>

            <div class="flex items-center gap-2 flex-shrink-0 sm:justify-end">
                <label class="hidden sm:flex items-center mr-3">
                    <Checkbox
                        v-model:checked="onlyFavourites"
                        @change="showOnlyFavourites"
                        class="mr-2"
                    />
                    Only Favorites
                </label>

                <label v-if="mobileSelectionMode" class="flex items-center text-xs sm:hidden">
                    <Checkbox
                        v-model:checked="allSelected"
                        @change="onSelectAllChange"
                        class="mr-2"
                    />
                    Select all
                </label>

                <div class="relative sm:hidden dropdown-menu">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white p-2 text-gray-900 shadow-sm hover:bg-gray-50"
                        @click.stop="toggleMobileActions"
                        aria-label="Open actions"
                    >
                        <EllipsisVerticalIcon class="w-5 h-5" />
                    </button>

                    <div
                        v-if="showMobileActions"
                        class="absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
                    >
                        <button
                            type="button"
                            class="block w-full px-4 py-3 text-left text-sm text-gray-700 hover:bg-blue-50"
                            @click="openShareModal"
                        >
                            Share
                        </button>
                        <button
                            type="button"
                            class="block w-full px-4 py-3 text-left text-sm text-gray-700 hover:bg-blue-50"
                            @click="downloadSelected"
                        >
                            Download
                        </button>
                        <button
                            type="button"
                            class="block w-full px-4 py-3 text-left text-sm text-red-600 hover:bg-red-50"
                            @click="openDeleteConfirm"
                        >
                            Delete
                        </button>
                    </div>
                </div>

                <div class="hidden sm:block">
                    <ShareFileButton
                        :all-selected="allSelected"
                        :selected-ids="selectedIds"
                    />
                </div>

                <div class="hidden sm:block">
                    <DownloadFileButton
                        :all="allSelected"
                        :ids="selectedIds"
                        class="mr-2"
                    />
                </div>

                <div class="hidden sm:block">
                    <DeleteFileButton
                        :delete-all="allSelected"
                        :delete-ids="selectedIds"
                        @delete="onDelete"
                    />
                </div>
            </div>
        </nav>

        <div class="flex-1 overflow-auto">
            <!-- Mobile: card list -->
            <div class="block sm:hidden">
                <div
                    v-for="file in allFiles.data"
                    :key="file.id"
                    class="bg-white rounded shadow mb-3 p-3 cursor-pointer hover:bg-blue-50"
                    @click="handleMobileCardTap(file)"
                    @touchstart.passive="startLongPress(file)"
                    @touchend="cancelLongPress"
                    @touchcancel="cancelLongPress"
                >
                    <div class="flex items-start gap-3">
                        <div v-if="mobileSelectionMode" class="pt-1">
                            <Checkbox
                                v-model="selected[file.id]"
                                :checked="selected[file.id] || allSelected"
                                @change="() => onSelectCheckboxChange(file)"
                            />
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2 min-w-0">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <FileIcon :file="file" class="flex-shrink-0" />
                                    <div class="text-sm font-medium text-gray-900 min-w-0 flex-1">
                                        <button
                                            v-if="renamingFile !== file.id && isPreviewableFile(file)"
                                            type="button"
                                            class="block w-full truncate text-left"
                                            @click.stop.prevent="previewFileAction(file)"
                                        >
                                            {{ file.name }}
                                        </button>
                                        <span v-else-if="renamingFile !== file.id" class="block truncate">{{ file.name }}</span>
                                        <input
                                            v-else
                                            :id="`rename-input-${file.id}`"
                                            v-model="newFileName"
                                            @keydown="handleRenameKeydown($event, file)"
                                            @blur="saveRename(file)"
                                            @click.stop
                                            class="w-full px-2 py-1 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            type="text"
                                        />
                                    </div>
                                </div>

                                <div class="relative dropdown-menu flex-shrink-0">
                                    <button
                                        @click.stop="toggleDropdown(file.id)"
                                        class="p-1 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <EllipsisVerticalIcon class="w-5 h-5 text-gray-500" />
                                    </button>

                                    <div
                                        v-if="openDropdown === file.id"
                                        class="absolute right-0 z-10 mt-1 w-36 bg-white border border-gray-200 rounded-md shadow-lg"
                                    >
                                        <div class="py-1">
                                            <button
                                                v-if="isPreviewableFile(file)"
                                                @click="previewFileAction(file)"
                                                class="w-full px-4 py-2 text-left text-sm font-medium tracking-wider text-gray-700 hover:bg-blue-100 transition"
                                            >
                                                Preview
                                            </button>
                                            <button
                                                @click="renameFile(file)"
                                                class="w-full px-4 py-2 text-left text-sm font-medium tracking-wider text-gray-700 hover:bg-blue-100 transition"
                                            >
                                                Rename
                                            </button>
                                            <button
                                                @click="moveFile(file)"
                                                class="w-full px-4 py-2 text-left text-sm font-medium tracking-wider text-gray-700 hover:bg-blue-100 transition"
                                            >
                                                Move
                                            </button>
                                            <button
                                                @click="copyFile(file)"
                                                class="w-full px-4 py-2 text-left text-sm font-medium tracking-wider text-gray-700 hover:bg-blue-100 transition"
                                            >
                                                Copy
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2 text-xs text-gray-600 flex flex-wrap gap-3">
                                <div class="truncate">{{ file.owner }}</div>
                                <div>{{ file.size }}</div>
                                <div>{{ file.updated_at }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop: table -->
            <div class="hidden sm:block">
            <table
                class="w-full text-sm text-left text-gray-500 rounded  shadow"
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
                        <th class=""></th>
                        <th class="pl-6 pr-0 py-3 w-7 max-w-7">Name</th>
                        <th class="px-6 py-3 hidden sm:table-cell" v-if="search">Path</th>
                        <th class="px-6 py-3 hidden sm:table-cell">Owner</th>
                        <th class="px-6 py-3 hidden sm:table-cell">Size</th>
                        <th class="px-6 py-3 hidden sm:table-cell">Last Modified</th>
                        <th class="px-6 py-3">Actions</th>
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
                        @dblclick="isPreviewableFile(file) ? previewFileAction(file) : openFolder(file)"
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
                            class="py-4 font-medium tracking-wider text-gray-900 whitespace-nowrap"
                        >
                            <div
                                class="flex items-center"
                                @click.stop.prevent="toggleFavourite(file)"
                            >
                                <StarOutlineIcon
                                    v-if="!file.is_favourite"
                                    class="w-4 h-4"
                                />
                                <StarSolidIcon
                                    v-else
                                    class="w-4 h-4 text-yellow-500"
                                />
                            </div>
                        </td>
                        <td
                            class="px-6 py-4 font-medium tracking-wider text-gray-900 whitespace-nowrap"
                        >
                            <div class="flex items-center">
                                <button
                                    v-if="isPreviewableFile(file)"
                                    type="button"
                                    class="mr-2 flex h-8 w-8 items-center justify-center overflow-hidden rounded border border-gray-300 bg-white"
                                    @click.stop.prevent="previewFileAction(file)"
                                >
                                    <img
                                        v-if="isPreviewableImage(file)"
                                        :src="route('files.preview', { file: file.id })"
                                        :alt="file.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <FileIcon v-else :file="file" class="h-5 w-5" />
                                </button>
                                <FileIcon v-else :file="file" />
                                <button
                                    v-if="renamingFile !== file.id && isPreviewableFile(file)"
                                    type="button"
                                    class="ml-1 block truncate text-left"
                                    @click.stop.prevent="previewFileAction(file)"
                                >
                                    {{ file.name }}
                                </button>
                                <span v-else-if="renamingFile !== file.id">{{ file.name }}</span>
                                <input
                                    v-else
                                    :id="`rename-input-${file.id}`"
                                    v-model="newFileName"
                                    @keydown="handleRenameKeydown($event, file)"
                                    @blur="saveRename(file)"
                                    @click.stop
                                    class="ml-1 px-2 py-1 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    type="text"
                                />
                            </div>
                        </td>
                        <td
                            v-if="search"
                            class="px-6 py-4 font-medium tracking-wider text-gray-900 whitespace-nowrap hidden sm:table-cell"
                        >
                            {{ file.path }}
                        </td>
                        <td
                            class="px-6 py-4 font-medium tracking-wider text-gray-900 whitespace-nowrap hidden sm:table-cell"
                        >
                            {{ file.owner }}
                        </td>
                        <td
                            class="px-6 py-4 font-medium tracking-wider text-gray-900 whitespace-nowrap hidden sm:table-cell"
                        >
                            {{ file.size }}
                        </td>
                        <td
                            class="px-6 py-4 font-medium tracking-wider text-gray-900 whitespace-nowrap hidden sm:table-cell"
                        >
                            {{ file.updated_at }}
                        </td>
                        <td
                            class="px-6 py-4  font-medium tracking-wider text-gray-900 whitespace-nowrap relative"
                        >
                            <div class="dropdown-menu relative">
                                <button
                                    @click.stop="toggleDropdown(file.id)"
                                    class="p-1 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <EllipsisVerticalIcon class="w-5 h-5 text-gray-500" />
                                </button>
                                
                                <div
                                    v-if="openDropdown === file.id"
                                    class="absolute left-0 z-10 top-full mt-1 w-36 bg-white border border-gray-200 rounded-md shadow-lg z-50"
                                >
                                    <div class="py-1">
                                        <button
                                            v-if="isPreviewableFile(file)"
                                            @click="previewFileAction(file)"
                                            class="w-full px-4 py-2 text-left text-sm font-medium tracking-wider text-gray-700 hover:bg-blue-100 transition ease-in-out duration-200"
                                        >
                                            Preview
                                        </button>
                                       
                                    </div>
                                    <div class="py-1">
                                        <button
                                            @click="renameFile(file)"
                                            class="w-full px-4 py-2 text-left text-sm font-medium tracking-wider text-gray-700 hover:bg-blue-100 transition ease-in-out duration-200"
                                        >
                                            Rename
                                        </button>
                                       
                                    </div>
                                    <div class="py-1">
                                         <button
                                            @click="moveFile(file)"
                                            class="w-full px-4 py-2 text-left text-sm font-medium tracking-wider text-gray-700 hover:bg-blue-100 transition ease-in-out duration-200"
                                        >
                                            Move
                                        </button>
                                        
                                    </div>
                                    <div class="py-1">
                                        <button
                                            @click="copyFile(file)"
                                            class="w-full px-4 py-2 text-left text-sm font-medium tracking-wider text-gray-700 hover:bg-blue-100 transition ease-in-out duration-200"
                                        >
                                            Copy
                                        </button>
                                    </div>
                                </div>
                            </div>
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
        </div>
        
        <!-- Move File Modal -->
        <ShareFilesModal
            v-model="showShareModal"
            :all-selected="allSelected"
            :selected-ids="selectedIds"
        />

        <MoveFileModal 
            :show="showMoveModal" 
            :file="fileToMove" 
            @close="onMoveModalClose"
            @moved="onFileMoved"
        />

        <ConfirmationDialog
            message="Are you sure you want to delete selected files? This method cannot be undone."
            :show="showDeleteConfirm"
            @cancel="closeDeleteConfirm"
            @confirm="confirmDelete"
        />

        <ImagePreviewModal
            v-model="showImagePreviewModal"
            :preview-src="previewFile ? route('files.preview', { file: previewFile.id }) : ''"
            :file-name="previewFile?.name || 'Image preview'"
            @update:modelValue="closeImagePreview"
        />
    </AuthenticatedLayout>
</template>
