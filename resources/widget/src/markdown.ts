/**
 * Just enough markdown for support answers: paragraphs, lists, **bold**,
 * `code` and [n] citation markers. Everything is escaped first, so nothing
 * the model or an agent writes can become markup.
 */
export function render(text: string): string {
    const escaped = text.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);

    const inline = (line: string): string =>
        line
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>')
            .replace(/\[(\d+)\]/g, '<sup>$1</sup>');

    const html: string[] = [];
    let list: 'ul' | 'ol' | null = null;

    const closeList = (): void => {
        if (list) html.push(`</${list}>`);
        list = null;
    };

    for (const block of escaped.split(/\n{2,}/)) {
        for (const line of block.split('\n')) {
            const bullet = line.match(/^\s*[-*]\s+(.*)$/);
            const numbered = line.match(/^\s*\d+[.)]\s+(.*)$/);
            const item = bullet ?? numbered;

            if (item) {
                const kind = bullet ? 'ul' : 'ol';
                if (list !== kind) {
                    closeList();
                    html.push(`<${kind}>`);
                    list = kind;
                }
                html.push(`<li>${inline(item[1])}</li>`);
                continue;
            }

            closeList();
            const heading = line.match(/^#{1,6}\s+(.*)$/);
            if (heading) html.push(`<p><b>${inline(heading[1])}</b></p>`);
            else if (line.trim()) html.push(`<p>${inline(line)}</p>`);
        }

        closeList();
    }

    return html.join('');
}
