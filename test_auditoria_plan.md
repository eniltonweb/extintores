1. **Add new test `testAuditoriaCacheHit` to `tests/AuditoriaTest.php`**
   - We will call `auditoria($acao, $codigo_extintor, $user_id, $user_level, $detalhes)` twice with the same extintor code.
   - We will assert that only 1 `SELECT` query is executed in total.
   - We will assert that 2 `INSERT` queries are executed.
   - This validates that the `$extintor_cache` is working and avoids N+1 queries.
2. **Complete pre-commit steps to ensure proper testing, verification, review, and reflection are done.**
   - Follow instructions from `pre_commit_instructions`.
3. **Submit the Pull Request**
   - We'll commit and submit the code with the required PR title format `🧪 [testing improvement description]` and the `🎯 **What:**`, `📊 **Coverage:**`, and `✨ **Result:**` format in the PR description.
