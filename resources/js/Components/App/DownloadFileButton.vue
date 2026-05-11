<script setup>
import { usePage } from "@inertiajs/vue3";
import { ArrowDownCircleIcon } from "@heroicons/vue/24/outline";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import { computed } from "vue";

const props = defineProps({
    all: {
        type: Boolean,
        required: false,
        default: false,
    },
    ids: {
        type: Array,
        required: false,
    },
    sharedWithMe: false,
    sharedByMe: false,
});

const page = usePage();

const btnDisabled = computed(() => {
    return !props.all && !props.ids.length;
});

const download = () => {
    if (!props.all && !props.ids.length) {
        return;
    }

    const urlParams = new URLSearchParams();
    if (page.props.rootFolder?.id) {
        urlParams.append("parent_id", page.props.rootFolder.id);
    }
    if (props.all) {
        urlParams.append("all", props.all ? 1 : 0);
    } else {
        for (let id of props.ids) {
            urlParams.append("ids[]", id);
        }
    }

    let url = route("files.download");
    if (props.sharedWithMe) {
        url = route("files.downloadSharedWithMe");
    } else if (props.sharedByMe) {
        url = route("files.downloadSharedByMe");
    }

    // Direct download by redirecting to the URL
    // The browser will handle the file download response automatically
    window.location.href = `${url}?${urlParams.toString()}`;
};
</script>

<template>
    <PrimaryButton
        @click="download"
        :disabled="btnDisabled"
        :class="btnDisabled ? 'disabled:opacity-50 cursor-not-allowed' : ''"
    >
        <ArrowDownCircleIcon class="w-4 h-4 mr-2" /> Download
    </PrimaryButton>
</template>
