function color_from_string(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    const h = Math.abs(hash) % 360;
    return `hsl(${h}, 70%, 60%)`;
}

function clear_previous_labels(svg) {
    svg.querySelectorAll('rect[data-annotated], text[data-annotated]').forEach(el => el.remove());
}

function get_category_for_annotation(rect, img) {
    const divs = document.querySelectorAll('.r6o-editor');
    const imgRect = img.getBoundingClientRect();
    const scaleX = rect.ownerSVGElement.viewBox.baseVal.width / imgRect.width;
    const scaleY = rect.ownerSVGElement.viewBox.baseVal.height / imgRect.height;

    const x = parseFloat(rect.getAttribute('x'));
    const y = parseFloat(rect.getAttribute('y'));
    const width = parseFloat(rect.getAttribute('width'));
    const height = parseFloat(rect.getAttribute('height'));

    for (let div of divs) {
        const divRect = div.getBoundingClientRect();
        const relX = (divRect.left - imgRect.left) * scaleX;
        const relY = (divRect.top - imgRect.top) * scaleY;
        const relW = divRect.width * scaleX;
        const relH = divRect.height * scaleY;

        if (relX + relW > x && relX < x + width && relY + relH > y && relY < y + height) {
            const labelSpan = div.querySelector('.r6o-label');
            if (labelSpan) return labelSpan.textContent.trim();
        }
    }
    return 'unknown';
}

function annotate_svg(svg, img) {
    if (!svg || !img) return;

    clear_previous_labels(svg);

    svg.querySelectorAll('g.a9s-annotation').forEach((g) => {
        const rect = g.querySelector('rect.a9s-inner');
        if (!rect) return;

        const category = get_category_for_annotation(rect, img);

        const x = parseFloat(rect.getAttribute('x'));
        const y = parseFloat(rect.getAttribute('y'));
        const width = parseFloat(rect.getAttribute('width'));

        const color = color_from_string(category);

        const bg = document.createElementNS("http://www.w3.org/2000/svg", "rect");
        bg.setAttribute("x", x);
        bg.setAttribute("y", y - 20);
        bg.setAttribute("width", Math.min(width, category.length * 10));
        bg.setAttribute("height", 20);
        bg.setAttribute("fill", color);
        bg.setAttribute("opacity", 0.7);
        bg.setAttribute("data-annotated", "1");

        const text = document.createElementNS("http://www.w3.org/2000/svg", "text");
        text.setAttribute("x", x + 2);
        text.setAttribute("y", y - 5);
        text.setAttribute("fill", "#fff");
        text.setAttribute("font-size", "14");
        text.setAttribute("font-family", "Arial, sans-serif");
        text.textContent = category;
        text.setAttribute("data-annotated", "1");

        svg.appendChild(bg);
        svg.appendChild(text);
    });
}

function throttle(func, limit) {
    let lastCall = 0;
    return function() {
        const now = Date.now();
        if (now - lastCall >= limit) {
            lastCall = now;
            func.apply(this, arguments);
        }
    };
}

function watch_svg_auto() {
    // Bild und SVG automatisch suchen
    const img = Array.from(document.images).find(i => i.src.includes('print_image.php?filename='));
    const svg = document.querySelector('svg.a9s-annotationlayer');

    if (!img || !svg) {
        // Falls noch nicht geladen, retry nach kurzer Zeit
        setTimeout(watch_svg_auto, 500);
        return;
    }

    const throttledUpdate = throttle(() => annotate_svg(svg, img), 200); // max 5x/sec

    const observer = new MutationObserver(throttledUpdate);
    observer.observe(svg, { childList: true, subtree: true, attributes: true });

    annotate_svg(svg, img);
}

// automatisch starten
watch_svg_auto();
