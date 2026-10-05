import { Pipe, PipeTransform } from '@angular/core';

@Pipe({ name: 'markdownPreview' })
export class MarkdownPreviewPipe implements PipeTransform {
  transform(value: string | null | undefined): string {
    if (!value) {
      return '';
    }

    return value
      .split('\n')
      .map((line) => line.replace(/^#{1,6}\s*/, '').replace(/^[-*]\s*/, ''))
      .join(' ')
      .replace(/\s+/g, ' ')
      .trim();
  }
}
