# D016 · The list sorts by newest, oldest or priority, and carries both lists' counts
Date: 2026-11-02 · Goal: G-004 M1 · Status: active (superseded by: —)
Context: the new design has a Newest / Oldest / Priority control and a link to the archive showing how many notes it holds.
Decision: `GET /api/notes` takes `sort=newest|oldest|priority` (422 otherwise; blank = newest) and returns `meta.counts` = active and archived totals, unfiltered.
Force: requirement for the sort and the count: the product request (apply the supplied design, which shows both, and include sort); judgment for putting the counts in the list response.
Rejected: a separate counts endpoint (a second request on every list load, and the two answers could disagree); sorting in the browser (wrong once the list is paginated).
Consequence: "newest" means last updated in the wall and last archived in the archive; priority sort breaks ties newest first. Any future pagination must keep the same orders.
Evidence: `backend/tests/Integration/NotesApiTest.php` ("testListSortsAndCarriesTheCountsOfBothLists")
