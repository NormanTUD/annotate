async function run_additional_tests() {
    console.log("\n=== RUNNING ADDITIONAL TESTS ===");

    let passed = 0;
    let failed = 0;

    function assert(condition, msg) {
        if (condition) {
            console.log("✓ PASS:", msg);
            passed++;
        } else {
            console.error("✖ FAIL:", msg);
            failed++;
        }
    }

    // Predefined boxes for IOU tests
    const boxA = [0, 0, 10, 10];
    const boxB = [5, 5, 15, 15];

    // --- uuidv4 additional ---
    console.log("\n--- uuidv4 additional ---");
    const id = uuidv4();
    assert(id.split('-').length === 5, "uuidv4 has 5 segments separated by '-'");
    assert(id.toLowerCase() === id, "uuidv4 is lowercase");

    // --- getNewURL additional ---
    console.log("\n--- getNewURL additional ---");
    assert(getNewURL("http://example.com?x=1&y=2", "z", null).includes("z=null"), "getNewURL handles null value");
    assert(getNewURL("http://example.com?x=1", "x", undefined).includes("x=undefined"), "getNewURL handles undefined value");
    assert(getNewURL("http://example.com?x=1", "x", "").includes("x="), "getNewURL handles empty string value");

    // --- iou additional ---
    console.log("\n--- iou additional ---");
    const boxZero = [0,0,0,0];
    assert(iou(boxZero, boxA) === 0, "iou with zero-size box = 0");
    assert(iou(boxA, boxZero) === 0, "iou commutes with zero-size box");
    assert(iou([0,0,1,1], [1,1,2,2]) === 0, "iou boxes touching at corner = 0");

    // --- getShape additional ---
    console.log("\n--- getShape additional ---");
    assert(JSON.stringify(getShape([[[1],[2]], [[3],[4]]])) === JSON.stringify([2,2,1]), "getShape non-uniform but rectangular 3D array");
    assert(JSON.stringify(getShape([[[1,2], [3,4]], [[5,6], [7,8]]])) === JSON.stringify([2,2,2]), "getShape 3D uniform array");

    // --- get_names_from_ki_anno additional ---
    console.log("\n--- get_names_from_ki_anno additional ---");
    const sampleAnno6 = [{body:[{value:null}, {value:""}]}];
    const names6 = get_names_from_ki_anno(sampleAnno6);
    assert(names6[null] === 1 && names6[""] === 1, "get_names_from_ki_anno handles null and empty string");

    // --- get_annotate_element additional ---
    console.log("\n--- get_annotate_element additional ---");
    if ($("#image").length > 0) {
        const a = get_annotate_element("test", 0, 0, 1, 1);
        assert(a.id.length > 1, "annotation ID exists");
        assert(a.body[0].value === "test", "annotation label correct");
        const a2 = get_annotate_element("test", 0, 0, 1, 1, "extra");
        assert(a2 !== null, "annotation accepts extra parameters gracefully");
    }

    console.log(`\n=== ADDITIONAL TESTS DONE: ${passed} passed, ${failed} failed ===`);
    return failed;
}

async function run_tests() {
	console.log("=== RUNNING TESTS ===");

	let passed = 0;
	let failed = 0;

	function assert(condition, msg) {
		if (condition) {
			console.log("✓ PASS:", msg);
			passed++;
		} else {
			console.error("✖ FAIL:", msg);
			failed++;
		}
	}

	// --- uuidv4 ---
	console.log("\n--- Testing uuidv4 ---");
	const id1 = uuidv4();
	const id2 = uuidv4();
	assert(typeof id1 === "string", "uuidv4 returns a string");
	assert(id1.length === 36, "uuidv4 returns correct length (36 chars)");
	assert(id1 !== id2, "uuidv4 produces unique values");
	assert(/^[0-9a-f-]+$/.test(id1), "uuidv4 contains only valid hex chars and dashes");

	// --- getNewURL ---
	console.log("\n--- Testing getNewURL ---");
	const url1 = getNewURL("http://example.com?a=1", "a", "2");
	assert(url1 === "http://example.com?a=2", "getNewURL updates existing parameter");

	const url2 = getNewURL("http://example.com", "b", "5");
	assert(url2 === "http://example.com?b=5", "getNewURL adds new parameter to URL without params");

	const url3 = getNewURL("http://example.com?x=1&y=2", "y", "99");
	assert(url3.includes("y=99") && url3.includes("x=1"), "getNewURL updates param while keeping others");

	const url4 = getNewURL("http://example.com?a=1&b=2&c=3", "b", "new");
	assert(url4.includes("a=1") && url4.includes("b=new") && url4.includes("c=3"), "getNewURL handles multiple params");

	const url5 = getNewURL("http://example.com?test=hello%20world", "test", "goodbye");
	assert(url5.includes("test=goodbye"), "getNewURL handles encoded values");

	// --- iou (Intersection over Union) ---
	console.log("\n--- Testing iou ---");
	const boxA = [0, 0, 10, 10];
	const boxB = [5, 5, 15, 15];
	const boxC = [20, 20, 30, 30];
	const boxD = [0, 0, 10, 10]; // identical to boxA
	const boxE = [0, 0, 5, 5]; // contained in boxA

	assert(iou(boxA, boxB) > 0 && iou(boxA, boxB) < 1, "iou overlapping boxes between 0 and 1");
	assert(iou(boxA, boxC) === 0, "iou non-overlapping boxes = 0");
	assert(iou(boxA, boxD) === 1, "iou identical boxes = 1");
	assert(Math.abs(iou(boxA, boxB) - 0.142857) < 0.001, "iou calculates correct value for partial overlap");
	assert(iou(boxA, boxE) < 1 && iou(boxA, boxE) > 0, "iou handles contained boxes");
	assert(iou(boxB, boxA) === iou(boxA, boxB), "iou is commutative");

	// Edge cases
	const boxTiny = [0, 0, 0.1, 0.1];
	const boxHuge = [0, 0, 1000, 1000];
	assert(iou(boxTiny, boxHuge) >= 0 && iou(boxTiny, boxHuge) <= 1, "iou handles very different sizes");

	// --- getShape ---
	console.log("\n--- Testing getShape ---");
	const arr1 = [[1,2],[3,4]];
	const arr2 = [[[1,2],[3,4]],[[5,6],[7,8]]];
	const arr3 = [1,2,3,4];
	const arr4 = [];
	const arr5 = [[[[[1]]]]];

	assert(JSON.stringify(getShape(arr1)) === JSON.stringify([2,2]), "getShape works for 2D array");
	assert(JSON.stringify(getShape(arr2)) === JSON.stringify([2,2,2]), "getShape works for 3D array");
	assert(JSON.stringify(getShape(arr3)) === JSON.stringify([4]), "getShape works for 1D array");
	assert(JSON.stringify(getShape(arr4)) === JSON.stringify([0]), "getShape handles empty array");
	assert(JSON.stringify(getShape(arr5)) === JSON.stringify([1,1,1,1,1]), "getShape handles deeply nested arrays");

	// --- get_names_from_ki_anno ---
	console.log("\n--- Testing get_names_from_ki_anno ---");
	const sampleAnno1 = [
		{ body: [{ value: "Alice" }, { value: "Bob" }] },
		{ body: [{ value: "Alice" }] }
	];
	const names1 = get_names_from_ki_anno(sampleAnno1);
	assert(names1["Alice"] === 2 && names1["Bob"] === 1, "get_names_from_ki_anno counts correctly");

	const sampleAnno2 = [];
	const names2 = get_names_from_ki_anno(sampleAnno2);
	assert(Object.keys(names2).length === 0, "get_names_from_ki_anno handles empty array");

	const sampleAnno3 = [
		{ body: [{ value: "Cat" }, { value: "Cat" }, { value: "Dog" }] }
	];
	const names3 = get_names_from_ki_anno(sampleAnno3);
	assert(names3["Cat"] === 2 && names3["Dog"] === 1, "get_names_from_ki_anno counts duplicates in same annotation");

	const sampleAnno4 = [
		{ body: [{ value: "A" }] },
		{ body: [{ value: "B" }] },
		{ body: [{ value: "C" }] },
		{ body: [{ value: "A" }] },
		{ body: [{ value: "B" }] },
		{ body: [{ value: "A" }] }
	];
	const names4 = get_names_from_ki_anno(sampleAnno4);
	assert(names4["A"] === 3 && names4["B"] === 2 && names4["C"] === 1, "get_names_from_ki_anno handles multiple different counts");

	// Edge cases
	const sampleAnno5 = [{ body: [] }];
	const names5 = get_names_from_ki_anno(sampleAnno5);
	assert(Object.keys(names5).length === 0, "get_names_from_ki_anno handles empty body");

	// --- getUrlParam ---
	console.log("\n--- Testing getUrlParam ---");
	const defaultVal = getUrlParam('nonexistent_param', 42);
	assert(defaultVal === 42, "getUrlParam returns default for non-existent param");

	const nanVal = getUrlParam('string_param', 100);
	assert(nanVal === 100, "getUrlParam returns default for non-numeric param");

	// --- sleep ---
	console.log("\n--- Testing sleep ---");
	const startTime = Date.now();
	await sleep(100);
	const elapsed = Date.now() - startTime;
	assert(elapsed >= 100 && elapsed < 150, "sleep waits approximately correct time");

	const startTime2 = Date.now();
	await sleep(0);
	const elapsed2 = Date.now() - startTime2;
	assert(elapsed2 < 50, "sleep(0) returns quickly");

	// --- get_annotate_element (validation) ---
	console.log("\n--- Testing get_annotate_element validation ---");
	const badAnno1 = get_annotate_element("dog", -1, 0, 10, 10);
	assert(badAnno1 === null, "get_annotate_element rejects negative x_start");

	const badAnno2 = get_annotate_element("dog", 0, -5, 10, 10);
	assert(badAnno2 === null, "get_annotate_element rejects negative y_start");

	const badAnno3 = get_annotate_element("dog", 0, 0, 0, 10);
	assert(badAnno3 === null, "get_annotate_element rejects zero width");

	const badAnno4 = get_annotate_element("dog", 0, 0, 10, -10);
	assert(badAnno4 === null, "get_annotate_element rejects negative height");

	const badAnno5 = get_annotate_element("dog", 1.5, 0, 10, 10);
	assert(badAnno5 === null, "get_annotate_element rejects float x_start");

	const badAnno6 = get_annotate_element("dog", 0, 1.5, 10, 10);
	assert(badAnno6 === null, "get_annotate_element rejects float y_start");

	const badAnno7 = get_annotate_element("dog", 0, 0, 10.5, 10);
	assert(badAnno7 === null, "get_annotate_element rejects float width");

	const badAnno8 = get_annotate_element("dog", 0, 0, 10, 10.5);
	assert(badAnno8 === null, "get_annotate_element rejects float height");

	// Valid call (needs DOM element to be present)
	if ($("#image").length > 0) {
		const goodAnno = get_annotate_element("cat", 10, 20, 100, 200);
		assert(goodAnno !== null && goodAnno.type === "Annotation", "get_annotate_element returns valid annotation object");
		assert(goodAnno.body[0].value === "cat", "get_annotate_element sets correct label");
		assert(goodAnno.target.selector.value === "xywh=pixel:10,20,100,200", "get_annotate_element formats coordinates correctly");
		assert(goodAnno.id.startsWith("#"), "get_annotate_element generates ID with # prefix");
		assert(goodAnno["@context"] === "http://www.w3.org/ns/anno.jsonld", "get_annotate_element sets correct context");

		const goodAnno2 = get_annotate_element("bird", 0, 0, 1, 1);
		assert(goodAnno2 !== null, "get_annotate_element accepts minimal valid box (1x1)");
	}

	// --- processModelOutput ---
	console.log("\n--- Testing processModelOutput ---");
	if (typeof imgsz !== 'undefined') {
		// Test 1: Basic filtering by confidence
		const mockOutput1 = [
			[
				[400, 200],      // x values for 2 predictions
				[400, 200],      // y values
				[100, 50],       // w values
				[100, 50],       // h values
				[0.1, 0.2],      // class 0 scores
				[0.9, 0.1]       // class 1 scores
			]
		];

		const result1 = processModelOutput(mockOutput1, 0.5, 0.5);
		assert(result1.boxes.length === 1, "processModelOutput filters low confidence predictions");
		assert(result1.classes[0] === 1, "processModelOutput selects correct class");
		assert(result1.scores[0] === 0.9, "processModelOutput returns correct score");

		// Test 2: Multiple high confidence predictions
		const mockOutput2 = [
			[
				[400, 500, 600],    // x
				[400, 500, 600],    // y
				[100, 100, 100],    // w
				[100, 100, 100],    // h
				[0.8, 0.7, 0.9],    // class 0
				[0.1, 0.2, 0.05]    // class 1
			]
		];

		const result2 = processModelOutput(mockOutput2, 0.5, 0.5);
		assert(result2.boxes.length === 3, "processModelOutput keeps all high confidence predictions");
		assert(result2.classes.every(c => c === 0), "processModelOutput selects class 0 for all");

		// Test 3: NMS - overlapping boxes
		const mockOutput3 = [
			[
				[400, 405],         // Nearly same position
				[400, 405],
				[100, 100],
				[100, 100],
				[0.9, 0.8],         // Both high confidence
				[0.1, 0.1]
			]
		];

		const result3 = processModelOutput(mockOutput3, 0.5, 0.5);
		assert(result3.boxes.length === 1, "processModelOutput applies NMS to remove overlapping boxes");
		assert(result3.scores[0] === 0.9, "processModelOutput keeps highest scoring box after NMS");

		// Test 4: Empty result (all below threshold)
		const mockOutput4 = [
			[
				[400],
				[400],
				[100],
				[100],
				[0.1],
				[0.2]
			]
		];

		const result4 = processModelOutput(mockOutput4, 0.5, 0.5);
		assert(result4.boxes.length === 0, "processModelOutput returns empty when all below threshold");

		// Test 5: Box coordinates conversion
		const mockOutput5 = [
			[
				[400],     // center x at 400 (imgsz=800 -> 0.5)
				[400],     // center y at 400 (imgsz=800 -> 0.5)
				[200],     // width 200 (imgsz=800 -> 0.25)
				[200],     // height 200 (imgsz=800 -> 0.25)
				[0.8],
				[0.1]
			]
		];

		const result5 = processModelOutput(mockOutput5, 0.5, 0.5);
		assert(result5.boxes.length === 1, "processModelOutput processes coordinates");
		const [cx, cy, bw, bh] = result5.boxes[0];
		assert(cx === 0.5 && cy === 0.5, "processModelOutput converts to relative center coordinates");
		assert(bw === 0.25 && bh === 0.25, "processModelOutput converts to relative dimensions");

		// Test 6: Different confidence thresholds
		const mockOutput6 = [
			[
				[400, 400, 400],
				[400, 400, 400],
				[100, 100, 100],
				[100, 100, 100],
				[0.3, 0.5, 0.7],
				[0.1, 0.1, 0.1]
			]
		];

		const result6b = processModelOutput(mockOutput6, 0.6, 0.5);
		assert(result6b.boxes.length === 1, "processModelOutput with conf=0.6 keeps 1 box");

		// Test 7: Multiple classes
		const mockOutput7 = [
			[
				[400, 500],
				[400, 500],
				[100, 100],
				[100, 100],
				[0.1, 0.2],    // class 0
				[0.3, 0.8],    // class 1
				[0.7, 0.1]     // class 2
			]
		];

		const result7 = processModelOutput(mockOutput7, 0.5, 0.5);
		assert(result7.boxes.length === 2, "processModelOutput handles multiple classes");
	}

	// --- Coordinate transformations (inline tests) ---
	console.log("\n--- Testing coordinate transformations ---");

	// Test conversion from [x1,y1,x2,y2] to [cx,cy,w,h]
	function testXYXYtoCXCYWH(x1, y1, x2, y2, expected_cx, expected_cy, expected_w, expected_h) {
		const cx = (x1 + x2) / 2;
		const cy = (y1 + y2) / 2;
		const w = x2 - x1;
		const h = y2 - y1;
		return cx === expected_cx && cy === expected_cy && w === expected_w && h === expected_h;
	}

	assert(testXYXYtoCXCYWH(0, 0, 10, 10, 5, 5, 10, 10), "converts [0,0,10,10] to [5,5,10,10]");
	assert(testXYXYtoCXCYWH(5, 5, 15, 15, 10, 10, 10, 10), "converts [5,5,15,15] to [10,10,10,10]");
	assert(testXYXYtoCXCYWH(0, 0, 100, 50, 50, 25, 100, 50), "converts [0,0,100,50] to [50,25,100,50]");

	// Test conversion from [cx,cy,w,h] to [x,y,w,h] (top-left format)
	function testCXCYWHtoXYWH(cx, cy, w, h, expected_x, expected_y) {
		const x = cx - w / 2;
		const y = cy - h / 2;
		return x === expected_x && y === expected_y;
	}

	assert(testCXCYWHtoXYWH(5, 5, 10, 10, 0, 0), "converts [5,5,10,10] to [0,0]");
	assert(testCXCYWHtoXYWH(10, 10, 10, 10, 5, 5), "converts [10,10,10,10] to [5,5]");
	assert(testCXCYWHtoXYWH(0.5, 0.5, 0.2, 0.2, 0.4, 0.4), "converts relative coords [0.5,0.5,0.2,0.2] to [0.4,0.4]");

	// --- Best class selection ---
	console.log("\n--- Testing best class selection logic ---");

	function getBestClass(classScores) {
		let bestScore = -Infinity;
		let bestClass = -1;
		for (let c = 0; c < classScores.length; c++) {
			if (classScores[c] > bestScore) {
				bestScore = classScores[c];
				bestClass = c;
			}
		}
		return { bestClass, bestScore };
	}

	const bc1 = getBestClass([0.1, 0.9, 0.3]);
	assert(bc1.bestClass === 1 && bc1.bestScore === 0.9, "getBestClass selects middle class");

	const bc2 = getBestClass([0.9, 0.1, 0.1]);
	assert(bc2.bestClass === 0 && bc2.bestScore === 0.9, "getBestClass selects first class");

	const bc3 = getBestClass([0.1, 0.1, 0.9]);
	assert(bc3.bestClass === 2 && bc3.bestScore === 0.9, "getBestClass selects last class");

	const bc4 = getBestClass([0.5, 0.5, 0.5]);
	assert(bc4.bestClass === 0 && bc4.bestScore === 0.5, "getBestClass selects first when all equal");

	const bc5 = getBestClass([-0.1, -0.2, -0.05]);
	assert(bc5.bestClass === 2 && bc5.bestScore === -0.05, "getBestClass handles negative scores");

	const bc6 = getBestClass([0.5]);
	assert(bc6.bestClass === 0 && bc6.bestScore === 0.5, "getBestClass handles single class");

	const bc7 = getBestClass([]);
	assert(bc7.bestClass === -1 && bc7.bestScore === -Infinity, "getBestClass handles empty array");

	console.log(`\n=== TESTS DONE: ${passed} passed, ${failed} failed ===`);

	assert(await run_additional_tests() == 0, "additional tests failed");

	return failed;
}
