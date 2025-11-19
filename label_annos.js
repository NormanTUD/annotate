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

    let x = parseFloat(rect.getAttribute('x'));
    let y = parseFloat(rect.getAttribute('y'));
    let width = parseFloat(rect.getAttribute('width'));
    let height = parseFloat(rect.getAttribute('height'));

    for (let div of divs) {
        const divRect = div.getBoundingClientRect();
        // div position relativ zum Bild
        const relX = (divRect.left - imgRect.left) * scaleX;
        const relY = (divRect.top - imgRect.top) * scaleY;
        const relW = divRect.width * scaleX;
        const relH = divRect.height * scaleY;

        // Überlappung prüfen
        if (relX + relW > x && relX < x + width && relY + relH > y && relY < y + height) {
            const labelSpan = div.querySelector('.r6o-label');
            if (labelSpan) return labelSpan.textContent.trim();
        }
    }
    return 'unknown';
}

function annotate_svg(svg_selector, img_selector) {
    const svg = document.querySelector(svg_selector);
    const img = document.querySelector(img_selector);
    if (!svg || !img) return;

    clear_previous_labels(svg);

    const annotations = svg.querySelectorAll('g.a9s-annotation');
    console.log("Found annotations:", annotations.length);

    annotations.forEach((g, idx) => {
        const rect = g.querySelector('rect.a9s-inner');
        if (!rect) return;

        const category = get_category_for_annotation(rect, img);
        console.log(`Annotation ${idx}: category=${category}`);

        const x = parseFloat(rect.getAttribute('x'));
        const y = parseFloat(rect.getAttribute('y'));
        const width = parseFloat(rect.getAttribute('width'));

        const color = color_from_string(category);

        // Hintergrund
        const bg = document.createElementNS("http://www.w3.org/2000/svg", "rect");
        bg.setAttribute("x", x);
        bg.setAttribute("y", y - 20);
        bg.setAttribute("width", Math.min(width, category.length * 10));
        bg.setAttribute("height", 20);
        bg.setAttribute("fill", color);
        bg.setAttribute("opacity", 0.7);
        bg.setAttribute("data-annotated", "1");

        // Text
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

// Debounce-Funktion, verhindert Endlosschleifen
function debounce(func, wait) {
    let timeout;
    return function() {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, arguments), wait);
    };
}

// Automatisches Update
function watch_svg(svg_selector, img_selector) {
    const svg = document.querySelector(svg_selector);
    if (!svg) return;

    const debouncedUpdate = debounce(() => {
        console.log("SVG changed, updating annotations...");
        annotate_svg(svg_selector, img_selector);
    }, 100); // 100ms debounce

    const observer = new MutationObserver(debouncedUpdate);
    observer.observe(svg, { childList: true, subtree: true, attributes: true });

    // initial draw
    annotate_svg(svg_selector, img_selector);
}
