```markdown
# bookly-travel Development Patterns

> Auto-generated skill from repository analysis

## Overview
This skill teaches you the core development patterns and conventions used in the `bookly-travel` TypeScript codebase. You'll learn about file naming, import/export styles, commit patterns, and how to write and run tests. This guide is ideal for contributors seeking to maintain consistency and quality in the project.

## Coding Conventions

### File Naming
- **Pattern:** PascalCase
- **Example:**  
  `UserProfile.ts`, `BookingService.ts`

### Import Style
- **Pattern:** Mixed (both default and named imports)
- **Examples:**
  ```typescript
  import BookingService from './BookingService';
  import { calculatePrice, formatDate } from './utils/DateUtils';
  ```

### Export Style
- **Pattern:** Mixed (both default and named exports)
- **Examples:**
  ```typescript
  // Default export
  export default class BookingService { ... }

  // Named export
  export function calculatePrice(...) { ... }
  ```

### Commit Patterns
- **Type:** Freeform (no strict prefixes)
- **Average Length:** ~27 characters
- **Example:**  
  `fix booking price calculation`

## Workflows

### Adding a New Feature
**Trigger:** When implementing a new functionality  
**Command:** `/add-feature`

1. Create a new TypeScript file using PascalCase (e.g., `NewFeature.ts`).
2. Use default or named exports as appropriate.
3. Import dependencies using mixed style.
4. Write corresponding tests in a `.test.ts` file.
5. Commit changes with a concise, descriptive message.

### Fixing a Bug
**Trigger:** When resolving a bug  
**Command:** `/fix-bug`

1. Locate the relevant TypeScript file.
2. Apply the bug fix, maintaining code style.
3. Update or add tests in the corresponding `.test.ts` file.
4. Commit with a clear message describing the fix.

### Writing Tests
**Trigger:** When adding or updating tests  
**Command:** `/write-test`

1. Create or update a test file with the `.test.ts` suffix (e.g., `BookingService.test.ts`).
2. Follow existing test patterns (see Testing Patterns below).
3. Ensure tests cover new or changed functionality.

## Testing Patterns

- **Test File Pattern:** `*.test.ts`
- **Testing Framework:** Unknown (use standard TypeScript test conventions)
- **Example:**
  ```typescript
  // BookingService.test.ts
  import BookingService from './BookingService';

  describe('BookingService', () => {
    it('calculates price correctly', () => {
      const service = new BookingService();
      expect(service.calculatePrice(...)).toBe(...);
    });
  });
  ```

## Commands
| Command      | Purpose                                      |
|--------------|----------------------------------------------|
| /add-feature | Start the workflow for adding a new feature  |
| /fix-bug     | Begin the bug fixing workflow                |
| /write-test  | Guide for writing or updating tests          |
```
