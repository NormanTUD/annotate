function color_from_string(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    const h = Math.abs(hash) % 360;
    return `hsl(${h}, 70%, 60%)`;
}

function clear_previous_labels(svg) {
    // Lösche alle Text- und Rect-Elemente, die wir hinzugefügt haben
    svg.querySelectorAll('rect[data-annotated], text[data-annotated]').forEach(el => el.remove());
}

function annotate_svg(svg_selector, img_selector) {
    const svg = document.querySelector(svg_selector);
    const img = document.querySelector(img_selector);

    if (!svg || !img) {
        console.warn("SVG or image not found!");
        return;
    }

    // Alte Labels entfernen
    clear_previous_labels(svg);

    const annotations = svg.querySelectorAll('g.a9s-annotation');
    console.log("Found annotations:", annotations.length);

    // Skalierung Bild -> SVG
    const scaleX = svg.viewBox.baseVal.width / img.clientWidth;
    const scaleY = svg.viewBox.baseVal.height / img.clientHeight;

    annotations.forEach((g, index) => {
        console.group(`Annotation ${index}`);

        const rect = g.querySelector('rect.a9s-inner');
        if (!rect) {
            console.warn("No inner rect found, skipping.");
            console.groupEnd();
            return;
        }

        const x = parseFloat(rect.getAttribute('x'));
        const y = parseFloat(rect.getAttribute('y'));
        const width = parseFloat(rect.getAttribute('width'));
        const height = parseFloat(rect.getAttribute('height'));
        console.log("Box coords:", x, y, width, height);

        // TODO: Kategorie aus Divs ermitteln
        let category = 'TODO'; 

        const color = color_from_string(category);

        // Hintergrund für Text
        const bg = document.createElementNS("http://www.w3.org/2000/svg", "rect");
        bg.setAttribute("x", x);
        bg.setAttribute("y", y - 20);
        bg.setAttribute("width", Math.min(width, category.length * 10));
        bg.setAttribute("height", 20);
        bg.setAttribute("fill", color);
        bg.setAttribute("opacity", 0.7);
        bg.setAttribute("data-annotated", "1"); // markiert zum späteren Löschen

        // Text
        const text = document.createElementNS("http://www.w3.org/2000/svg", "text");
        text.setAttribute("x", x + 2);
        text.setAttribute("y", y - 5);
        text.setAttribute("fill", "#fff");
        text.setAttribute("font-size", "14");
        text.setAttribute("font-family", "Arial, sans-serif");
        text.textContent = category;
        text.setAttribute("data-annotated", "1"); // markiert zum späteren Löschen

        svg.appendChild(bg);
        svg.appendChild(text);
        console.groupEnd();
    });
}
