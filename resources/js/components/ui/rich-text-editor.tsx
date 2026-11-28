import React, { useEffect, useRef, useState } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Strike } from '@tiptap/extension-strike';
import { Highlight } from '@tiptap/extension-highlight';
import { TextAlign } from '@tiptap/extension-text-align';
import { Link } from '@tiptap/extension-link';
import { Color } from '@tiptap/extension-color';
import { TextStyle } from '@tiptap/extension-text-style';
import { TaskList, TaskItem } from '@tiptap/extension-list';
import { Placeholder, CharacterCount } from '@tiptap/extensions';
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table';
import { Image } from '@tiptap/extension-image';
import { Subscript } from '@tiptap/extension-subscript';
import { Superscript } from '@tiptap/extension-superscript';
import { Typography } from '@tiptap/extension-typography';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
  Bold,
  Italic,
  Underline as UnderlineIcon,
  Strikethrough,
  Highlighter,
  AlignLeft,
  AlignCenter,
  AlignRight,
  AlignJustify,
  List,
  ListOrdered,
  ListChecks,
  Quote,
  Code,
  Code2,
  Minus,
  Undo,
  Redo,
  Link as LinkIcon,
  Image as ImageIcon,
  Table as TableIcon,
  Subscript as SubscriptIcon,
  Superscript as SuperscriptIcon,
  RemoveFormatting,
  Maximize2,
  Minimize2,
  FolderOpen,
  Trash2,
  Plus,
} from 'lucide-react';
import { Button } from './button';
import { Input } from './input';
import { Label } from './label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from './select';
import { Popover, PopoverContent, PopoverTrigger } from './popover';
import MediaLibraryModal from '../MediaLibraryModal';
import { cn } from '@/lib/utils';
import { getImagePath, isRichTextEmpty } from '@/utils/helpers';

export interface RichTextEditorProps {
  id?: string;
  content?: string | null;
  onChange?: (content: string) => void;
  placeholder?: string;
  className?: string;
  disabled?: boolean;
  onKeyDown?: (e: React.KeyboardEvent) => void;
  variant?: 'full' | 'compact' | 'minimal';
  minHeight?: string;
  maxLength?: number;
  showWordCount?: boolean;
  enableMediaLibrary?: boolean;
}

const COLOR_SWATCHES = [
  '#000000',
  '#374151',
  '#DC2626',
  '#EA580C',
  '#CA8A04',
  '#16A34A',
  '#0284C7',
  '#2563EB',
  '#7C3AED',
  '#DB2777',
];

const HIGHLIGHT_SWATCHES = [
  '#FEF08A',
  '#BBF7D0',
  '#BAE6FD',
  '#E9D5FF',
  '#FECDD3',
  '#FED7AA',
];

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function normalizeEditorInput(raw?: string | null): string {
  if (!raw) return '';
  const trimmed = String(raw).trim();
  if (!trimmed) return '';

  const hasHtml = /<\/?(p|br|div|ul|ol|li|h[1-6]|table|blockquote|pre|strong|b|em|i|u|s|a|img|hr|span)\b/i.test(trimmed);
  if (hasHtml) {
    return trimmed;
  }

  // Convert legacy plain text newlines into semantic paragraphs cleanly
  return trimmed
    .split(/\r?\n/)
    .map((line) => (line.trim() === '' ? '<p></p>' : `<p>${escapeHtml(line)}</p>`))
    .join('');
}

export function RichTextEditor({
  id,
  content = '',
  onChange,
  placeholder,
  className,
  disabled = false,
  onKeyDown,
  variant = 'full',
  minHeight,
  maxLength,
  showWordCount,
  enableMediaLibrary = true,
}: RichTextEditorProps) {
  const { t } = useTranslation();

  let hasAuthenticatedUser = false;
  try {
    const page = usePage().props as any;
    hasAuthenticatedUser = Boolean(page?.auth?.user);
  } catch {
    hasAuthenticatedUser = false;
  }

  const [isFullscreen, setIsFullscreen] = useState(false);
  const [linkPopoverOpen, setLinkPopoverOpen] = useState(false);
  const [linkUrl, setLinkUrl] = useState('');
  const [imagePopoverOpen, setImagePopoverOpen] = useState(false);
  const [imageUrl, setImageUrl] = useState('');
  const [imageAlt, setImageAlt] = useState('');
  const [isMediaModalOpen, setIsMediaModalOpen] = useState(false);

  const lastEmittedRef = useRef<string>(content ?? '');
  const resolvedMinHeight = minHeight || (variant === 'compact' ? 'min-h-[100px]' : variant === 'minimal' ? 'min-h-[84px]' : 'min-h-[150px]');
  const displayWordCount = showWordCount ?? variant === 'full';

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        bulletList: {
          keepMarks: true,
          keepAttributes: false,
        },
        orderedList: {
          keepMarks: true,
          keepAttributes: false,
        },
        heading: {
          levels: [1, 2, 3, 4],
        },
        strike: false,
        link: false,
      }),
      Strike,
      Highlight.configure({ multicolor: true }),
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
      Link.configure({
        openOnClick: false,
        autolink: true,
        HTMLAttributes: {
          class: 'text-blue-600 dark:text-blue-400 underline cursor-pointer hover:opacity-80',
          rel: 'noopener noreferrer',
          target: '_blank',
        },
      }),
      Color,
      TextStyle,
      Subscript,
      Superscript,
      Typography,
      TaskList,
      TaskItem.configure({ nested: true }),
      Table.configure({ resizable: false }),
      TableRow,
      TableHeader,
      TableCell,
      Image.configure({
        inline: false,
        allowBase64: false,
        HTMLAttributes: {
          class: 'rounded-md max-w-full h-auto my-2',
        },
      }),
      Placeholder.configure({
        placeholder: placeholder || t('Start typing...'),
      }),
      ...(maxLength ? [CharacterCount.configure({ limit: maxLength })] : [CharacterCount]),
    ],
    content: normalizeEditorInput(content),
    onUpdate: ({ editor: activeEditor }) => {
      const html = activeEditor.getHTML();
      const outgoing = isRichTextEmpty(html) ? '' : html;
      lastEmittedRef.current = outgoing;
      onChange?.(outgoing);
    },
    editorProps: {
      attributes: {
        ...(id ? { id } : {}),
        class: cn(
          'focus:outline-none p-3 prose prose-sm dark:prose-invert max-w-none',
          '[&_ul]:list-disc [&_ul]:ml-6 [&_ol]:list-decimal [&_ol]:ml-6 [&_li]:my-0.5',
          resolvedMinHeight
        ),
      },
    },
    editable: !disabled,
  });

  // Keep editable state synced if disabled prop toggles
  useEffect(() => {
    if (editor && editor.isEditable !== !disabled) {
      editor.setEditable(!disabled);
    }
  }, [editor, disabled]);

  // Sync external content updates (e.g., AI Assistant generation, async Edit modal load, form reset)
  useEffect(() => {
    if (!editor) return;
    const incoming = content ?? '';
    if (incoming === lastEmittedRef.current) {
      return;
    }

    const currentHtml = isRichTextEmpty(editor.getHTML()) ? '' : editor.getHTML();
    const normalizedIncoming = normalizeEditorInput(incoming);
    if (incoming === '' && currentHtml === '') {
      lastEmittedRef.current = '';
      return;
    }

    if (normalizedIncoming !== editor.getHTML()) {
      editor.commands.setContent(normalizedIncoming || '', { emitUpdate: false });
      lastEmittedRef.current = incoming;
    }
  }, [content, editor]);

  if (!editor) return null;

  const currentBlockValue = editor.isActive('heading', { level: 1 })
    ? 'h1'
    : editor.isActive('heading', { level: 2 })
      ? 'h2'
      : editor.isActive('heading', { level: 3 })
        ? 'h3'
        : editor.isActive('heading', { level: 4 })
          ? 'h4'
          : 'paragraph';

  const handleBlockChange = (val: string) => {
    const chain = editor.chain().focus();
    if (val === 'paragraph') {
      chain.setParagraph().run();
    } else if (val === 'h1') {
      chain.toggleHeading({ level: 1 }).run();
    } else if (val === 'h2') {
      chain.toggleHeading({ level: 2 }).run();
    } else if (val === 'h3') {
      chain.toggleHeading({ level: 3 }).run();
    } else if (val === 'h4') {
      chain.toggleHeading({ level: 4 }).run();
    }
  };

  const openLinkEditor = () => {
    const existing = editor.getAttributes('link').href || '';
    setLinkUrl(existing);
    setLinkPopoverOpen(true);
  };

  const applyLink = () => {
    const trimmed = linkUrl.trim();
    if (!trimmed) {
      editor.chain().focus().extendMarkRange('link').unsetLink().run();
      setLinkPopoverOpen(false);
      return;
    }

    if (/^\s*javascript:/i.test(trimmed)) {
      return;
    }

    const safeHref = /^(https?:|mailto:|tel:|\/)/i.test(trimmed) ? trimmed : `https://${trimmed}`;
    editor.chain().focus().extendMarkRange('link').setLink({ href: safeHref }).run();
    setLinkPopoverOpen(false);
  };

  const insertImageByUrl = (rawUrl: string, altText?: string) => {
    const trimmed = rawUrl.trim();
    if (!trimmed || /^\s*javascript:/i.test(trimmed)) return;
    const resolved = /^(https?:|\/)/i.test(trimmed) ? trimmed : getImagePath(trimmed);
    editor.chain().focus().setImage({ src: resolved, alt: altText || '' }).run();
    setImageUrl('');
    setImageAlt('');
    setImagePopoverOpen(false);
  };

  const handleMediaSelect = (selected: string | string[]) => {
    const firstUrl = Array.isArray(selected) ? selected[0] : selected;
    if (firstUrl) {
      insertImageByUrl(firstUrl, imageAlt);
    }
    setIsMediaModalOpen(false);
  };

  const charStorage = (editor.storage as any)?.characterCount;
  const charCount = charStorage?.characters?.() ?? 0;
  const wordCount = charStorage?.words?.() ?? 0;
  const insideTable = editor.isActive('table');

  return (
    <div
      className={cn(
        'border rounded-md bg-background transition-all',
        isFullscreen && 'fixed inset-4 z-50 flex flex-col shadow-2xl border-2',
        disabled && 'opacity-70 bg-muted/30',
        className
      )}
    >
      {/* Toolbar */}
      <div className="border-b px-2 py-1.5 flex flex-wrap items-center gap-0.5 bg-muted/20">
        {variant !== 'minimal' && (
          <>
            <Select value={currentBlockValue} onValueChange={handleBlockChange} disabled={disabled}>
              <SelectTrigger className="h-7 w-[112px] text-xs mr-1 border-border/70">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="paragraph">{t('Paragraph')}</SelectItem>
                <SelectItem value="h1">{t('Heading 1')}</SelectItem>
                <SelectItem value="h2">{t('Heading 2')}</SelectItem>
                <SelectItem value="h3">{t('Heading 3')}</SelectItem>
                <SelectItem value="h4">{t('Heading 4')}</SelectItem>
              </SelectContent>
            </Select>
            <div className="w-px h-5 bg-border mx-1" />
          </>
        )}

        <Button
          type="button"
          variant="ghost"
          size="sm"
          title={t('Bold')}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleBold().run()}
          className={cn('h-7 w-7 p-0', editor.isActive('bold') && 'bg-muted text-foreground')}
        >
          <Bold className="h-3.5 w-3.5" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          title={t('Italic')}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleItalic().run()}
          className={cn('h-7 w-7 p-0', editor.isActive('italic') && 'bg-muted text-foreground')}
        >
          <Italic className="h-3.5 w-3.5" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          title={t('Underline')}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleUnderline().run()}
          className={cn('h-7 w-7 p-0', editor.isActive('underline') && 'bg-muted text-foreground')}
        >
          <UnderlineIcon className="h-3.5 w-3.5" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          title={t('Strikethrough')}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleStrike().run()}
          className={cn('h-7 w-7 p-0', editor.isActive('strike') && 'bg-muted text-foreground')}
        >
          <Strikethrough className="h-3.5 w-3.5" />
        </Button>

        {variant === 'full' && (
          <>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              title={t('Subscript')}
              disabled={disabled}
              onClick={() => editor.chain().focus().toggleSubscript().run()}
              className={cn('h-7 w-7 p-0', editor.isActive('subscript') && 'bg-muted text-foreground')}
            >
              <SubscriptIcon className="h-3.5 w-3.5" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              title={t('Superscript')}
              disabled={disabled}
              onClick={() => editor.chain().focus().toggleSuperscript().run()}
              className={cn('h-7 w-7 p-0', editor.isActive('superscript') && 'bg-muted text-foreground')}
            >
              <SuperscriptIcon className="h-3.5 w-3.5" />
            </Button>
          </>
        )}

        {/* Text Color */}
        <Popover>
          <PopoverTrigger asChild>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              title={t('Text Color')}
              disabled={disabled}
              className="h-7 px-1.5 gap-1"
            >
              <span
                className="text-xs font-bold underline decoration-2"
                style={{ textDecorationColor: editor.getAttributes('textStyle').color || 'currentColor' }}
              >
                A
              </span>
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-48 p-2" align="start">
            <div className="grid grid-cols-5 gap-1.5 mb-2">
              {COLOR_SWATCHES.map((hex) => (
                <button
                  key={hex}
                  type="button"
                  onClick={() => editor.chain().focus().setColor(hex).run()}
                  className="h-6 w-6 rounded border border-border hover:scale-110 transition-transform"
                  style={{ backgroundColor: hex }}
                  title={hex}
                />
              ))}
            </div>
            <div className="flex items-center justify-between gap-2 pt-1 border-t">
              <input
                type="color"
                aria-label={t('Custom Color')}
                onInput={(e) => editor.chain().focus().setColor((e.target as HTMLInputElement).value).run()}
                value={editor.getAttributes('textStyle').color || '#000000'}
                className="h-6 w-10 border rounded cursor-pointer"
              />
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-6 text-xs px-2"
                onClick={() => editor.chain().focus().unsetColor().run()}
              >
                {t('Reset')}
              </Button>
            </div>
          </PopoverContent>
        </Popover>

        {/* Highlight */}
        <Popover>
          <PopoverTrigger asChild>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              title={t('Highlight')}
              disabled={disabled}
              className={cn('h-7 w-7 p-0', editor.isActive('highlight') && 'bg-muted text-foreground')}
            >
              <Highlighter className="h-3.5 w-3.5" />
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-44 p-2" align="start">
            <div className="flex flex-wrap gap-1.5 mb-2">
              {HIGHLIGHT_SWATCHES.map((hex) => (
                <button
                  key={hex}
                  type="button"
                  onClick={() => editor.chain().focus().toggleHighlight({ color: hex }).run()}
                  className="h-6 w-6 rounded border border-border hover:scale-110 transition-transform"
                  style={{ backgroundColor: hex }}
                  title={hex}
                />
              ))}
            </div>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-6 text-xs w-full"
              onClick={() => editor.chain().focus().unsetHighlight().run()}
            >
              {t('Clear Highlight')}
            </Button>
          </PopoverContent>
        </Popover>

        <div className="w-px h-5 bg-border mx-1" />

        {/* Lists */}
        <Button
          type="button"
          variant="ghost"
          size="sm"
          title={t('Bullet List')}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleBulletList().run()}
          className={cn('h-7 w-7 p-0', editor.isActive('bulletList') && 'bg-muted text-foreground')}
        >
          <List className="h-3.5 w-3.5" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          title={t('Numbered List')}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleOrderedList().run()}
          className={cn('h-7 w-7 p-0', editor.isActive('orderedList') && 'bg-muted text-foreground')}
        >
          <ListOrdered className="h-3.5 w-3.5" />
        </Button>
        {variant !== 'minimal' && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            title={t('Checklist')}
            disabled={disabled}
            onClick={() => editor.chain().focus().toggleTaskList().run()}
            className={cn('h-7 w-7 p-0', editor.isActive('taskList') && 'bg-muted text-foreground')}
          >
            <ListChecks className="h-3.5 w-3.5" />
          </Button>
        )}

        {variant !== 'minimal' && (
          <>
            <div className="w-px h-5 bg-border mx-1" />
            <Button
              type="button"
              variant="ghost"
              size="sm"
              title={t('Align Left')}
              disabled={disabled}
              onClick={() => editor.chain().focus().setTextAlign('left').run()}
              className={cn('h-7 w-7 p-0', editor.isActive({ textAlign: 'left' }) && 'bg-muted')}
            >
              <AlignLeft className="h-3.5 w-3.5" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              title={t('Align Center')}
              disabled={disabled}
              onClick={() => editor.chain().focus().setTextAlign('center').run()}
              className={cn('h-7 w-7 p-0', editor.isActive({ textAlign: 'center' }) && 'bg-muted')}
            >
              <AlignCenter className="h-3.5 w-3.5" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              title={t('Align Right')}
              disabled={disabled}
              onClick={() => editor.chain().focus().setTextAlign('right').run()}
              className={cn('h-7 w-7 p-0', editor.isActive({ textAlign: 'right' }) && 'bg-muted')}
            >
              <AlignRight className="h-3.5 w-3.5" />
            </Button>
            {variant === 'full' && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                title={t('Justify')}
                disabled={disabled}
                onClick={() => editor.chain().focus().setTextAlign('justify').run()}
                className={cn('h-7 w-7 p-0', editor.isActive({ textAlign: 'justify' }) && 'bg-muted')}
              >
                <AlignJustify className="h-3.5 w-3.5" />
              </Button>
            )}
          </>
        )}

        <div className="w-px h-5 bg-border mx-1" />

        {/* Blockquote, Code, HR */}
        <Button
          type="button"
          variant="ghost"
          size="sm"
          title={t('Quote')}
          disabled={disabled}
          onClick={() => editor.chain().focus().toggleBlockquote().run()}
          className={cn('h-7 w-7 p-0', editor.isActive('blockquote') && 'bg-muted')}
        >
          <Quote className="h-3.5 w-3.5" />
        </Button>

        {variant !== 'minimal' && (
          <>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              title={t('Inline Code')}
              disabled={disabled}
              onClick={() => editor.chain().focus().toggleCode().run()}
              className={cn('h-7 w-7 p-0', editor.isActive('code') && 'bg-muted')}
            >
              <Code className="h-3.5 w-3.5" />
            </Button>
            {variant === 'full' && (
              <>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  title={t('Code Block')}
                  disabled={disabled}
                  onClick={() => editor.chain().focus().toggleCodeBlock().run()}
                  className={cn('h-7 w-7 p-0', editor.isActive('codeBlock') && 'bg-muted')}
                >
                  <Code2 className="h-3.5 w-3.5" />
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  title={t('Horizontal Rule')}
                  disabled={disabled}
                  onClick={() => editor.chain().focus().setHorizontalRule().run()}
                  className="h-7 w-7 p-0"
                >
                  <Minus className="h-3.5 w-3.5" />
                </Button>
              </>
            )}
          </>
        )}

        {/* Link Popover */}
        <Popover open={linkPopoverOpen} onOpenChange={setLinkPopoverOpen}>
          <PopoverTrigger asChild>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              title={t('Insert Link')}
              disabled={disabled}
              onClick={openLinkEditor}
              className={cn('h-7 w-7 p-0', editor.isActive('link') && 'bg-muted')}
            >
              <LinkIcon className="h-3.5 w-3.5" />
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-72 p-3 space-y-2" align="start">
            <Label className="text-xs">{t('Link URL')}</Label>
            <Input
              value={linkUrl}
              onChange={(e) => setLinkUrl(e.target.value)}
              placeholder="https://..."
              className="h-8 text-xs"
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  applyLink();
                }
              }}
            />
            <div className="flex justify-end gap-1.5">
              {editor.isActive('link') && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="h-7 text-xs"
                  onClick={() => {
                    editor.chain().focus().extendMarkRange('link').unsetLink().run();
                    setLinkPopoverOpen(false);
                  }}
                >
                  {t('Remove')}
                </Button>
              )}
              <Button type="button" size="sm" className="h-7 text-xs" onClick={applyLink}>
                {t('Apply')}
              </Button>
            </div>
          </PopoverContent>
        </Popover>

        {/* Image & Media Library (full variant) */}
        {variant === 'full' && (
          <Popover open={imagePopoverOpen} onOpenChange={setImagePopoverOpen}>
            <PopoverTrigger asChild>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                title={t('Insert Image')}
                disabled={disabled}
                className="h-7 w-7 p-0"
              >
                <ImageIcon className="h-3.5 w-3.5" />
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-72 p-3 space-y-2" align="start">
              <Label className="text-xs">{t('Image URL')}</Label>
              <Input
                value={imageUrl}
                onChange={(e) => setImageUrl(e.target.value)}
                placeholder="https://..."
                className="h-8 text-xs"
              />
              <Label className="text-xs">{t('Alt Text')}</Label>
              <Input
                value={imageAlt}
                onChange={(e) => setImageAlt(e.target.value)}
                placeholder={t('Describe image...')}
                className="h-8 text-xs"
              />
              <div className="flex items-center justify-between gap-2 pt-1">
                {enableMediaLibrary && hasAuthenticatedUser ? (
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-7 text-xs gap-1"
                    onClick={() => {
                      setImagePopoverOpen(false);
                      setIsMediaModalOpen(true);
                    }}
                  >
                    <FolderOpen className="h-3 w-3" />
                    {t('Media Library')}
                  </Button>
                ) : (
                  <span />
                )}
                <Button
                  type="button"
                  size="sm"
                  className="h-7 text-xs"
                  disabled={!imageUrl.trim()}
                  onClick={() => insertImageByUrl(imageUrl, imageAlt)}
                >
                  {t('Insert')}
                </Button>
              </div>
            </PopoverContent>
          </Popover>
        )}

        {/* Table Controls (full variant) */}
        {variant === 'full' && (
          <Popover>
            <PopoverTrigger asChild>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                title={t('Table')}
                disabled={disabled}
                className={cn('h-7 w-7 p-0', insideTable && 'bg-muted text-foreground')}
              >
                <TableIcon className="h-3.5 w-3.5" />
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-56 p-2 space-y-1" align="start">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="w-full justify-start h-7 text-xs gap-1.5"
                onClick={() => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()}
              >
                <Plus className="h-3 w-3" />
                {t('Insert 3x3 Table')}
              </Button>
              {insideTable && (
                <>
                  <div className="border-t my-1" />
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="w-full justify-start h-7 text-xs"
                    onClick={() => editor.chain().focus().addRowAfter().run()}
                  >
                    {t('Add Row Below')}
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="w-full justify-start h-7 text-xs"
                    onClick={() => editor.chain().focus().addColumnAfter().run()}
                  >
                    {t('Add Column Right')}
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="w-full justify-start h-7 text-xs"
                    onClick={() => editor.chain().focus().deleteRow().run()}
                  >
                    {t('Delete Row')}
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="w-full justify-start h-7 text-xs"
                    onClick={() => editor.chain().focus().deleteColumn().run()}
                  >
                    {t('Delete Column')}
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="w-full justify-start h-7 text-xs"
                    onClick={() => editor.chain().focus().mergeOrSplit().run()}
                  >
                    {t('Merge / Split Cells')}
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="w-full justify-start h-7 text-xs text-red-600 hover:text-red-700 gap-1.5"
                    onClick={() => editor.chain().focus().deleteTable().run()}
                  >
                    <Trash2 className="h-3 w-3" />
                    {t('Delete Table')}
                  </Button>
                </>
              )}
            </PopoverContent>
          </Popover>
        )}

        <div className="flex-1" />

        {/* Utility buttons: Clear format, Undo, Redo, Fullscreen */}
        <Button
          type="button"
          variant="ghost"
          size="sm"
          title={t('Clear Formatting')}
          disabled={disabled}
          onClick={() => editor.chain().focus().unsetAllMarks().clearNodes().run()}
          className="h-7 w-7 p-0"
        >
          <RemoveFormatting className="h-3.5 w-3.5" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          title={t('Undo')}
          disabled={disabled || !editor.can().undo()}
          onClick={() => editor.chain().focus().undo().run()}
          className="h-7 w-7 p-0"
        >
          <Undo className="h-3.5 w-3.5" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          title={t('Redo')}
          disabled={disabled || !editor.can().redo()}
          onClick={() => editor.chain().focus().redo().run()}
          className="h-7 w-7 p-0"
        >
          <Redo className="h-3.5 w-3.5" />
        </Button>
        {variant !== 'minimal' && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            title={isFullscreen ? t('Exit Fullscreen') : t('Fullscreen')}
            onClick={() => setIsFullscreen((prev) => !prev)}
            className="h-7 w-7 p-0"
          >
            {isFullscreen ? <Minimize2 className="h-3.5 w-3.5" /> : <Maximize2 className="h-3.5 w-3.5" />}
          </Button>
        )}
      </div>

      {/* Content */}
      <div
        onKeyDown={onKeyDown}
        className={cn(
          'rich-text-content overflow-y-auto',
          isFullscreen ? 'flex-1' : 'max-h-[420px]'
        )}
      >
        <EditorContent editor={editor} />
      </div>

      {/* Footer status bar */}
      {displayWordCount && (
        <div className="border-t px-3 py-1 flex items-center justify-between text-[11px] text-muted-foreground bg-muted/10">
          <span>{wordCount} {t('words')}</span>
          <span>
            {charCount}
            {maxLength ? ` / ${maxLength}` : ''} {t('characters')}
          </span>
        </div>
      )}

      {enableMediaLibrary && hasAuthenticatedUser && isMediaModalOpen && (
        <MediaLibraryModal
          isOpen={isMediaModalOpen}
          onClose={() => setIsMediaModalOpen(false)}
          onSelect={handleMediaSelect}
          multiple={false}
        />
      )}
    </div>
  );
}
export default RichTextEditor;
