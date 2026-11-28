import React from 'react';
import { Input } from './input';
import { Label } from './label';
import { cn } from '@/lib/utils';

export interface TitleFieldProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'onChange'> {
  label?: string;
  required?: boolean;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  helperText?: string;
  showCharCount?: boolean;
  maxLength?: number;
  aiSlot?: React.ReactNode;
  containerClassName?: string;
}

/**
 * Standardized Title / Subject input: keeps indexed plain text searchable across
 * tables, Kanban cards, breadcrumbs, exports, and AI prompts while pairing cleanly with AI actions.
 */
export function TitleField({
  label,
  required = false,
  value = '',
  onChange,
  error,
  helperText,
  showCharCount = false,
  maxLength = 255,
  aiSlot,
  id,
  className,
  containerClassName,
  ...rest
}: TitleFieldProps) {
  return (
    <div className={cn('space-y-1', containerClassName)}>
      {(label || showCharCount) && (
        <div className="flex items-center justify-between gap-2">
          {label ? (
            <Label htmlFor={id} required={required}>
              {label}
            </Label>
          ) : <span />}
          {showCharCount && (
            <span className="text-[11px] text-muted-foreground">
              {(value || '').length}/{maxLength}
            </span>
          )}
        </div>
      )}
      <div className="flex items-end gap-2">
        <Input
          id={id}
          value={value ?? ''}
          maxLength={maxLength}
          onChange={(e) => onChange(e.target.value)}
          className={cn(error ? 'border-red-500' : '', className)}
          required={required}
          {...rest}
        />
        {aiSlot}
      </div>
      {helperText && !error && <p className="text-xs text-muted-foreground">{helperText}</p>}
      {error && <p className="text-sm text-red-500">{error}</p>}
    </div>
  );
}
export default TitleField;
