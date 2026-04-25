<!-- BEGIN:nextjs-agent-rules -->
# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` before writing any code. Heed deprecation notices.
<!-- END:nextjs-agent-rules -->

## Responsibilities
- **Task Orchestration**: Execute frontend implementation tasks by adhering to the established Next.js 16 App Router conventions.
- **State Management**: Ensure appropriate separation of Server Components and Client Components, handling UI state using standard React hooks natively or context providers.
- **Error Handling**: Build robust error boundaries and display user-friendly locale-aware (`next-intl`) fallback UI for API failures.
- **Styling**: Enforce Tailwind CSS constraints properly instead of vanilla CSS overrides.

## Capabilities
- **Read & Modify**: Full access to generate pages, components, hooks, and tests inside the `frontend/src/` directory.
- **Run Tasks**: Permitted to lint code structure using `npm run lint` and validate Typescript compilation.
- **Constraint Limits**: Native API integrations are restricted to authenticated frontend Next.js requests interacting generically over HTTP using Sanctum tokens—direct database access is strictly prohibited.

## Interfaces
- **Inputs**: Receive Natural Language task instructions tied to specific Next.js app domains (`[locale]/auth/`, layouts, etc.).
- **Outputs**: Generate valid React `.tsx` files, ensuring all outputs respect the pre-configured ESLint and TypeScript strict modes.
- **Expected Message Shapes**: Must adhere to defined `next-intl` localization message schemas (e.g., `messages/en.json`) when producing user-facing interfaces.
