async function run_tests() {
    console.log("=== RUNNING TESTS ===");

    let passed = 0;
    let failed = 0;

    function assert(condition, msg) {
        if (condition) {
            console.log("✔ PASS:", msg);
            passed++;
        } else {
            console.error("✖ FAIL:", msg);
            failed++;
        }
    }

    // --- uuidv4 ---
    const id1 = uuidv4();
    const id2 = uuidv4();
    assert(typeof id1 === "string", "uuidv4 returns a string");
    assert(id1 !== id2, "uuidv4 produces unique values");

    // --- getNewURL ---
    const url1 = getNewURL("http://example.com?a=1", "a", "2");
    assert(url1.includes("a=2"), "getNewURL updates parameter");
    const url2 = getNewURL("http://example.com", "b", "5");
    assert(url2.includes("b=5"), "getNewURL adds new parameter");

    // --- iou ---
    const boxA = [0, 0, 10, 10];
    const boxB = [5, 5, 15, 15];
    const boxC = [20, 20, 30, 30];
    assert(iou(boxA, boxB) > 0, "iou overlapping boxes > 0");
    assert(iou(boxA, boxC) === 0, "iou non-overlapping boxes = 0");

    // --- getShape ---
    const arr = [[1,2],[3,4]];
    assert(JSON.stringify(getShape(arr)) === JSON.stringify([2,2]), "getShape works");

    // --- get_annotate_element ---
    const annoElem = get_annotate_element("cat", 0, 0, 10, 10);
    assert(annoElem && annoElem.type === "Annotation", "get_annotate_element returns correct structure");

    const badAnno = get_annotate_element("dog", -1, 0, 10, 10);
    assert(badAnno === null, "get_annotate_element rejects invalid values");

    // --- get_names_from_ki_anno ---
    const sampleAnno = [
        { body: [{ value: "Alice" }, { value: "Bob" }] },
        { body: [{ value: "Alice" }] }
    ];
    const names = get_names_from_ki_anno(sampleAnno);
    assert(names["Alice"] === 2 && names["Bob"] === 1, "get_names_from_ki_anno counts correctly");

    // --- sleep ---
    const start = Date.now();
    await sleep(100);
    const elapsed = Date.now() - start;
    assert(elapsed >= 100, "sleep waits for at least the given time");

    console.log(`=== TESTS DONE: ${passed} passed, ${failed} failed ===`);

    return failed;
}
