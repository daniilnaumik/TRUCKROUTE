<template>
    <div class="tiptap-wrap">
        <!-- Toolbar -->
        <div class="tiptap-toolbar" v-if="editor">
            <!-- Block type -->
            <select class="tiptap-select" @change="setBlock($event.target.value)" :value="currentBlock">
                <option value="paragraph">Normal</option>
                <option value="h2">H2</option>
                <option value="h3">H3</option>
            </select>
            <div class="tiptap-divider"></div>
            <!-- Inline marks -->
            <button type="button" class="tiptap-btn" :class="{ 'is-active': editor.isActive('bold') }"        @click="editor.chain().focus().toggleBold().run()"          title="Bold"><b>B</b></button>
            <button type="button" class="tiptap-btn" :class="{ 'is-active': editor.isActive('italic') }"      @click="editor.chain().focus().toggleItalic().run()"        title="Italic"><i>I</i></button>
            <button type="button" class="tiptap-btn" :class="{ 'is-active': editor.isActive('underline') }"   @click="editor.chain().focus().toggleUnderline().run()"     title="Underline"><u>U</u></button>
            <button type="button" class="tiptap-btn" :class="{ 'is-active': editor.isActive('strike') }"      @click="editor.chain().focus().toggleStrike().run()"        title="Strike"><s>S</s></button>
            <div class="tiptap-divider"></div>
            <!-- Lists -->
            <button type="button" class="tiptap-btn" :class="{ 'is-active': editor.isActive('orderedList') }" @click="editor.chain().focus().toggleOrderedList().run()"  title="Numbered list">1.</button>
            <button type="button" class="tiptap-btn" :class="{ 'is-active': editor.isActive('bulletList') }"  @click="editor.chain().focus().toggleBulletList().run()"   title="Bullet list">•</button>
            <div class="tiptap-divider"></div>
            <!-- Special blocks -->
            <button type="button" class="tiptap-btn" :class="{ 'is-active': editor.isActive('blockquote') }"  @click="editor.chain().focus().toggleBlockquote().run()"   title="Quote">"</button>
            <button type="button" class="tiptap-btn" :class="{ 'is-active': editor.isActive('code') }"        @click="editor.chain().focus().toggleCode().run()"          title="Code">&lt;/&gt;</button>
            <div class="tiptap-divider"></div>
            <!-- Link -->
            <button type="button" class="tiptap-btn" :class="{ 'is-active': editor.isActive('link') }"        @click="setLink"                                            title="Link">🔗</button>
            <!-- Image URL -->
            <button type="button" class="tiptap-btn" @click="insertImageUrl" title="Image">🖼</button>
            <!-- Video (YouTube) -->
            <button type="button" class="tiptap-btn" @click="insertYoutube" title="YouTube">▶</button>
            <div class="tiptap-divider"></div>
            <!-- Text align -->
            <button type="button" class="tiptap-btn" @click="editor.chain().focus().setTextAlign('left').run()"   title="Left">≡</button>
            <button type="button" class="tiptap-btn" @click="editor.chain().focus().setTextAlign('center').run()" title="Center">≡</button>
            <div class="tiptap-divider"></div>
            <!-- Clear -->
            <button type="button" class="tiptap-btn" @click="editor.chain().focus().clearMarks().run()" title="Clear formatting">Fx</button>
        </div>

        <!-- Editor area -->
        <EditorContent class="tiptap-content" :editor="editor" />
    </div>
</template>

<script setup>
import { watch, onBeforeUnmount, computed } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Youtube from '@tiptap/extension-youtube';
import TextAlign from '@tiptap/extension-text-align';
import Highlight from '@tiptap/extension-highlight';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Напишите описание...' },
    minHeight: { type: String, default: '200px' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit.configure({
            link: false,
            underline: false,
        }),
        Underline,
        Link.configure({ openOnClick: false }),
        Image,
        Youtube.configure({ width: 640, height: 360 }),
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Highlight,
    ],
    editorProps: {
        attributes: { class: 'tiptap-editor-inner', style: `min-height:${props.minHeight}` },
    },
    onUpdate({ editor }) {
        emit('update:modelValue', editor.getHTML());
    },
});

const currentBlock = computed(() => {
    if (!editor.value) return 'paragraph';
    if (editor.value.isActive('heading', { level: 2 })) return 'h2';
    if (editor.value.isActive('heading', { level: 3 })) return 'h3';
    return 'paragraph';
});

function setBlock(val) {
    if (!editor.value) return;
    const chain = editor.value.chain().focus();
    if (val === 'paragraph') chain.setParagraph().run();
    else if (val === 'h2') chain.setHeading({ level: 2 }).run();
    else if (val === 'h3') chain.setHeading({ level: 3 }).run();
}

function setLink() {
    const prev = editor.value.getAttributes('link').href;
    const url = window.prompt('URL ссылки:', prev ?? '');
    if (url === null) return;
    if (!url) { editor.value.chain().focus().unsetLink().run(); return; }
    editor.value.chain().focus().setLink({ href: url }).run();
}

function insertImageUrl() {
    const url = window.prompt('URL изображения:');
    if (url) editor.value.chain().focus().setImage({ src: url }).run();
}

function insertYoutube() {
    const url = window.prompt('YouTube URL:');
    if (url) editor.value.commands.setYoutubeVideo({ src: url });
}

// Sync external model → editor
watch(() => props.modelValue, (val) => {
    if (editor.value && editor.value.getHTML() !== val) {
        editor.value.commands.setContent(val, false);
    }
});

onBeforeUnmount(() => editor.value?.destroy());
</script>

<style scoped>
.tiptap-wrap {
    border: 1px solid var(--border-mid);
    border-radius: 8px;
    overflow: hidden;
    background: var(--bg);
}

.tiptap-toolbar {
    display: flex;
    align-items: center;
    gap: 2px;
    padding: 8px 10px;
    background: var(--s1);
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}

.tiptap-btn {
    min-height: auto;
    height: 30px;
    width: 30px;
    padding: 0;
    border: 1px solid transparent;
    border-radius: 4px;
    background: none;
    color: var(--text-2);
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: none;
    transition: background .12s, color .12s, border-color .12s;
}
.tiptap-btn:hover { background: var(--s3); color: var(--text); transform: none; box-shadow: none; }
.tiptap-btn.is-active { background: var(--accent-bg); color: var(--accent); border-color: var(--border-a); }

.tiptap-select {
    height: 30px;
    padding: 0 6px;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--s2);
    color: var(--text);
    font-size: 12px;
    cursor: pointer;
    min-height: auto;
}

.tiptap-divider {
    width: 1px;
    height: 20px;
    background: var(--border-mid);
    margin: 0 4px;
}
</style>

<style>
/* Global — Tiptap content styles */
.tiptap-content { padding: 0; }

.tiptap-editor-inner {
    padding: 14px 16px;
    outline: none;
    color: var(--text);
    font-size: 14px;
    line-height: 1.65;
}

.tiptap-editor-inner p { margin: 0 0 8px; }
.tiptap-editor-inner p:last-child { margin-bottom: 0; }
.tiptap-editor-inner h2 { font-size: 20px; font-family: var(--font-d); margin: 16px 0 8px; }
.tiptap-editor-inner h3 { font-size: 16px; font-family: var(--font-d); margin: 12px 0 6px; }
.tiptap-editor-inner ul, .tiptap-editor-inner ol { padding-left: 20px; margin: 8px 0; }
.tiptap-editor-inner li + li { margin-top: 3px; }
.tiptap-editor-inner blockquote { border-left: 3px solid var(--accent); margin: 12px 0; padding: 8px 16px; color: var(--text-2); }
.tiptap-editor-inner code { background: var(--s3); padding: 2px 6px; border-radius: 3px; font-family: var(--font-m); font-size: 12px; }
.tiptap-editor-inner a { color: var(--accent); text-decoration: underline; }
.tiptap-editor-inner img { max-width: 100%; border-radius: 4px; margin: 8px 0; }
.tiptap-editor-inner iframe { width: 100%; border-radius: 4px; margin: 8px 0; }
.tiptap-editor-inner p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: var(--text-3);
    pointer-events: none;
    height: 0;
}
</style>
