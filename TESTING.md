# ChatAI – Testing & Verification Notes

This document records **manual test scenarios** used to verify ChatAI behaviour during development.

It is **not** a comprehensive QA suite.  
Its purpose is to:

- Prevent regressions when new features are added
- Capture known edge cases
- Provide confidence that core behaviour remains intact

Tests are written from a **user/admin perspective**, not tied to internal implementation details.

---

## General Testing Rules

- Always start tests from a **clean chat session**  
  Use **Reset this chat** to clear frontend state and PHP session data.
- Do **not** change test conditions to force a pass  
  If a test fails, treat it as a real issue.
- Record only **stable, repeatable behaviour**
- If behaviour changes by design, **update the test**, not the code to fit it.

---

## Test Setup (Common)

- `max_messages = 4`
- Observability logging enabled
- Chat widget embedded on frontend pages
- Date range default: 7 days
- Browser session fresh or reset between tests

---

## TEST 1 – Near-limit warning (Error 10)

**Purpose**  
Ensure users receive a warning when only one model-backed message remains.

**Action**
1. Start a new chat
2. Ask 3 normal questions that trigger model-backed replies

**Expected**
- Q1, Q2: normal responses
- Q3:
    - Assistant reply renders
    - Error 10 warning bubble appears below the reply
    - Input remains enabled

**Actual**  
Behaved as expected.

**Result**  
Pass

---

## TEST 2 – Message limit reached (Error 4 / hard stop)

**Purpose**  
Ensure chat stops cleanly once message limit is exceeded.

**Action**
1. Continue from Test 1
2. Ask a 4th normal model-backed question

**Expected**
- Assistant reply renders (if allowed)
- Limit message displayed
- Input disabled or removed
- Reset link remains available

**Actual**  
Behaved as expected.

**Result**  
Pass

---

## TEST 3 – Small talk does not consume message credits

**Purpose**  
Ensure local/non-model paths do not affect message limits.

**Action**
1. Reset chat
2. Send 5 messages reliably classified as `small_talk`

**Expected**
- No message credit consumption
- No near-limit or limit warnings
- Input remains enabled

**Actual**  
Behaved as expected.

**Result**  
Pass

---

## TEST 4 – Soft blacklist does not consume message credits

**Purpose**  
Ensure soft blacklist warnings do not affect message limits.

**Action**
1. Reset chat
2. Trigger soft blacklist warnings (below hard threshold)

**Expected**
- Blacklist warning message displayed
- No Error 10 warning
- Message counter unchanged

**Actual**  
Behaved as expected.

**Result**  
Pass

---

## TEST 5 – Hard blacklist stop takes priority

**Purpose**  
Ensure hard blacklist blocks chat immediately.

**Action**
1. Trigger blacklist until hard stop condition is met

**Expected**
- Blocked message shown
- Input disabled or removed
- No Error 10 warning displayed

**Actual**  
Behaved as expected.

**Result**  
Pass

---

## TEST 6 – Cutoff takes priority over near-limit warning

**Purpose**  
Ensure cutoff UX overrides near-limit warnings.

**Action**  
Engineer a cutoff response when only one message remains.  
Example:  
“Summarise the last 10 pages about flowers into a checklist with headings and bullet points”

**Expected**
- Cutoff message styling displayed
- Input disabled or removed
- Error 10 not shown

**Actual**  
Behaved as expected.

**Result**  
Pass

---

## TEST 7 – Reset clears frontend and PHP session state

**Purpose**  
Ensure Reset starts a genuinely new chat.

**Action**
1. Reach message limit (Error 4)
2. Click **Reset this chat**
3. Ask a new question

**Expected**
- Full message quota restored
- No premature Error 10 or Error 4

**Actual**  
Behaved as expected.

**Result**  
Pass

---

## Notes & Known Design Decisions

- Message limits apply **only** to model-backed calls
- Cutoffs are treated as hard UX stops
- Near-limit warnings are informational only and do not block input
- Frontend reset clears:
    - `sessionStorage`
    - PHP session namespace `chatai` (via API call)

---

## When to Update This File

Update `TESTING.md` when:

- A user-visible behaviour changes
- A regression is fixed
- A new edge case is discovered
- A new feature affects message flow, limits, or logging
