# GitHub Flow Rules

### Branch Strategy

- 主分支：`master`
- 功能分支從 `master` 切出，完成後透過 Pull Request 合併回 `master`

---

### Commit Message Format

```
type: subject
```

**Subject 規範：**
- 全英文小寫，動詞開頭
- 多項變更以逗號分隔
- 不加句號，不超過 72 字

---

### Allowed Types

- `feat`: 新增/修改功能 (feature)
- `fix`: 修補 bug (bug fix)
- `docs`: 文件 (documentation)
- `style`: 格式（不影響程式碼運行的變動，如 white-space、formatting、missing semi colons 等）
- `refactor`: 重構（既不是新增功能，也不是修補 bug 的程式碼變動）
- `perf`: 改善效能 (a code change that improves performance)
- `test`: 增加測試 (when adding missing tests)
- `chore`: 建構程序或輔助工具的變動 (maintain)
- `revert`: 撤銷回覆先前的 commit，格式：`revert: type(scope): subject（回覆版本：xxxx）`

---

### Examples

```
feat: add order api, store order items
feat: add upload image api, setting r2
fix: resolve checkout price bug
refactor: simplify cart store logic
perf: optimize product query with eager loading
style: fix indentation in order controller
test: add cart item store test
chore: update dependencies
docs: update api reference, add order endpoints
revert: feat: add order api（回覆版本：a1b2c3d）
```
