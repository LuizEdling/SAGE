import { Pipe, PipeTransform } from '@angular/core';

/**
 * Texto de uma mensagem do chat pronto para `[innerHTML]`: escapa o HTML e
 * aplica só o que o Agente usa na conversa — `**negrito**` e `código`. As
 * quebras de linha já são preservadas pelo CSS da bolha. Mesma regra do widget
 * no GLPI (sage-chat.js), para a conversa ser vista igual nos dois lugares.
 */
@Pipe({ name: 'chatText' })
export class ChatTextPipe implements PipeTransform {
  transform(value: string | null | undefined): string {
    if (!value) {
      return '';
    }

    return value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;')
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/`([^`\n]+)`/g, '<code>$1</code>');
  }
}
