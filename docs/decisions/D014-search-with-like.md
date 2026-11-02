# D014 · Search is a substring LIKE per word, not a FULLTEXT index
Date: 2026-11-02 · Goal: G-003 M1 · Status: active (superseded by: —)
Context: the list needs a search over title and content that finds what a user types.
Decision: `?q=` is split on whitespace; every word must occur in the title or the content as an escaped `LIKE '%word%'`, which the `utf8mb4_uca1400_ai_ci` collation makes case- and accent-insensitive. At most 100 characters.
Force: judgment — a search box is expected to match parts of words ("meet" finds "meeting"); FULLTEXT matches whole words of three or more characters and drops stopwords.
Rejected: FULLTEXT with `MATCH … AGAINST` (no prefix or infix match, minimum word length, stopwords, relevance order that fights the date order); a search engine (a new service for one box).
Consequence: each search scans the user's own rows (the `user_id` index narrows to them). At thousands of notes per user, revisit FULLTEXT or an n-gram index.
Evidence: `backend/tests/Integration/NotesApiTest.php` ("testSearchIgnoresCaseAndAccentsAndTreatsWildcardsAsText")
