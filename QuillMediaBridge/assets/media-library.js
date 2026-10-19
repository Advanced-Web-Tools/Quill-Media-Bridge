const albumId = new URL(import.meta.url).searchParams.get('album_id');

// Quill owns the picker host; Media Library owns the authenticated, paged feed.
export default async function ({ container, type, value, onSelect, signal }) {
    const search = document.createElement('input');
    search.type = 'search';
    search.placeholder = 'Search media…';
    search.setAttribute('aria-label', 'Search media');
    search.maxLength = 255;
    search.style.cssText = 'width:100%;box-sizing:border-box;margin-bottom:12px;';
    const list = document.createElement('div');
    list.style.cssText = 'column-count:2;column-gap:8px;';
    const status = document.createElement('p');
    status.role = 'status';
    const more = document.createElement('button');
    more.type = 'button';
    more.textContent = 'Load more';
    more.hidden = true;
    container.append(search, list, status, more);
    let offset = 0;
    let selected = value;
    let revision = 0;
    let searchTimer;
    const buttons = new Map();

    function update({ value }) {
        selected = value;
        for (const [button, url] of buttons) button.setAttribute('aria-pressed', String(url === selected));
    }

    async function load() {
        const currentRevision = revision;
        more.disabled = true;
        status.textContent = 'Loading media…';
        try {
            const url = new URL('/dashboard/media/feed', window.location.origin);
            url.searchParams.set('kind', type);
            url.searchParams.set('offset', String(offset));
            url.searchParams.set('q', search.value.trim());
            if (albumId !== null) url.searchParams.set('album_id', albumId);
            const response = await fetch(url, { credentials: 'same-origin', signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Unable to load Media Library.');
            const { data: page } = await response.json();
            if (signal.aborted || currentRevision !== revision) return;
            if (!Array.isArray(page?.items)) throw new Error('Invalid Media Library response.');
            for (const item of page.items) {
                if (item.kind !== type || typeof item.source !== 'string' || !item.source.trim()) continue;
                const source = new URL(item.source, window.location.origin);
                if (!['http:', 'https:'].includes(source.protocol)) continue;
                const button = document.createElement('button');
                button.type = 'button';
                button.title = item.name;
                button.setAttribute('aria-label', item.name);
                button.style.cssText = 'display:block;width:100%;min-width:0;padding:0;margin:0 0 8px;break-inside:avoid;overflow:hidden;';
                if (type === 'image') {
                    const image = document.createElement('img');
                    image.src = item.source;
                    image.alt = '';
                    image.loading = 'lazy';
                    image.style.cssText = 'display:block;width:100%;height:auto;';
                    button.append(image);
                } else button.append(document.createTextNode(item.name));
                buttons.set(button, item.source);
                button.addEventListener('click', () => {
                    onSelect({ url: item.source, type, ...(type === 'image' ? { alt: item.name } : {}) });
                    update({ value: item.source });
                }, { signal });
                list.append(button);
            }
            update({ value: selected });
            offset = page.next_offset;
            more.hidden = !page.has_more;
            status.textContent = buttons.size ? '' : 'No media found.';
        } catch (error) {
            if (!signal.aborted && currentRevision === revision) throw error;
        } finally {
            if (currentRevision === revision) more.disabled = false;
        }
    }

    const reportError = error => { if (!signal.aborted) status.textContent = error.message; };
    search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        revision++;
        offset = 0;
        buttons.clear();
        list.replaceChildren();
        more.hidden = true;
        status.textContent = 'Loading media…';
        searchTimer = setTimeout(() => load().catch(reportError), 250);
    }, { signal });
    signal.addEventListener('abort', () => clearTimeout(searchTimer), { once: true });
    more.addEventListener('click', () => {
        load().catch(reportError);
    }, { signal });
    await load();
    return { update };
}
