import React, { useMemo } from 'react';
import DOMPurify from 'dompurify';
import { cn } from '@/lib/utils';
import { isRichTextEmpty } from '@/utils/helpers';

export interface RichTextViewerProps {
  content?: string | null;
  className?: string;
  compact?: boolean;
  fallback?: React.ReactNode;
}

/**
 * Safely render HTML authored via Tiptap (or legacy plain text with newlines)
 * using DOMPurify allowlisting and Typography dark-mode styles.
 */
export function RichTextViewer({
  content,
  className,
  compact = false,
  fallback = null,
}: RichTextViewerProps) {
  const sanitized = useMemo(() => {
    if (!content || isRichTextEmpty(content)) {
      return null;
    }

    const raw = String(content).trim();
    const looksLikeHtml = /<\/?(p|br|div|ul|ol|li|h[1-6]|table|blockquote|pre|strong|b|em|i|u|s|a|img|hr|span)\b/i.test(raw);

    if (!looksLikeHtml) {
      return { isPlain: true as const, text: raw };
    }

    const cleanHtml = DOMPurify.sanitize(raw, {
      ALLOWED_TAGS: [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'del',
        'sub', 'sup', 'mark', 'code', 'pre', 'blockquote', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div',
        'ul', 'ol', 'li', 'label', 'input',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'colgroup', 'col',
        'a', 'img',
      ],
      ALLOWED_ATTR: [
        'href', 'target', 'rel', 'src', 'alt', 'title', 'width', 'height',
        'style', 'class', 'colspan', 'rowspan', 'data-type', 'data-checked',
        'data-color', 'colwidth', 'type', 'checked', 'disabled',
      ],
      ALLOW_DATA_ATTR: false,
      FORBID_TAGS: ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'form', 'textarea', 'button'],
    });

    if (isRichTextEmpty(cleanHtml)) {
      return null;
    }

    return { isPlain: false as const, html: cleanHtml };
  }, [content]);

  if (!sanitized) {
    return <>{fallback}</>;
  }

  if (sanitized.isPlain) {
    return (
      <div className={cn('text-sm whitespace-pre-wrap break-words leading-relaxed', className)}>
        {sanitized.text}
      </div>
    );
  }

  return (
    <div
      className={cn(
        'rich-text-viewer prose prose-sm dark:prose-invert max-w-none break-words',
        '[&_ul]:list-disc [&_ul]:ml-5 [&_ol]:list-decimal [&_ol]:ml-5 [&_li]:my-0.5',
        '[&_a]:text-blue-600 dark:[&_a]:text-blue-400 [&_a]:underline hover:[&_a]:opacity-80',
        compact && 'prose-p:my-1 prose-headings:my-1.5 text-sm',
        className
      )}
      dangerouslySetInnerHTML={{ __html: sanitized.html }}
    />
  );
}
export default RichTextViewer;
