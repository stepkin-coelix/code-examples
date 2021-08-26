<template>
    <div class="input-file-wrapper" :class="{ 'is-dragover': isDragover }">
        <input type="file" ref="import_file" @change="input($event.target.files[0])" />
        <div class="info-container">

            <button class="button-transparent" type="button">
                <svg-icon icon="upload-cloud" />
                {{ browseFileTitle || $trans('common.drag_file.title')}}
            </button>
            <div>{{ $trans('common.drag_file.comment') }}</div>
        </div>
    </div>
</template>

<script>
export default {
    name: "FileInput",
    props: {
        'drop-text': {
            type: String,
            default: null
        },
        'browse-file-title': {
            type: String,
            default: null
        },
    },
    data() {
        return {
            file: null,
            isDragover: false,
        }
    },
    mounted() {
        if (this.isAdvancedUpload()) {

            ['dragover', 'dragenter'].forEach(event =>
                this.$el.addEventListener(event, () => this.isDragover = true));


            ['dragleave', 'dragend', 'drop'].forEach(event =>
                this.$el.addEventListener(event, () => this.isDragover = false));
        }
    },
    methods: {
        input(file) {
            this.$emit('input', file);
        },
        isAdvancedUpload() {
            let div = document.createElement('div');
            return (('draggable' in div) || ('ondragstart' in div && 'ondrop' in div)) && 'FormData' in window && 'FileReader' in window;
        }
    }
}
</script>

<style lang="scss" scoped>

$topaz: #ffc87c;
$cornsilk: #fff8dc;
$gray-100: #f8f9fa;

.input-file-wrapper {
    padding: 15px;
    position: relative;
    background-color: $cornsilk;
    background-size: 16px 16px;
    border: 3px dashed $topaz;
    text-align: center;
    cursor: pointer;
    border-radius: $input-border-radius;
    text-transform: uppercase;
    font-weight: $font-bold;
    font-size: $text-font-size;
    line-height: $text-font-size;
    display: flex;
    flex-direction: column;
    align-items: center;

    svg {
        color: inherit;
    }
    .info-container {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        & > *:not(:last-child) {
            margin-bottom: 5px;
        }
    }

    input {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        cursor: pointer;
        opacity: 0;
    }

    &.is-dragover {
        background-image: linear-gradient(135deg,
            $gray-100 25%,
            $white 25%,
            $white 50%,
            $gray-100 50%,
            $gray-100 75%,
            $white 75%,
            $white
        );
    }
}
</style>
