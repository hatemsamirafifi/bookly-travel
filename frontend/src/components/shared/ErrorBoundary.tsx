'use client';

import { Component, ReactNode, ErrorInfo } from 'react';
import * as Sentry from '@sentry/nextjs';

interface Props {
  children: ReactNode;
  fallback?: ReactNode;
}

interface State {
  hasError: boolean;
  errorId?: string;
}

export default class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(): State {
    return { hasError: true };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo) {
    const errorId = Sentry.captureException(error, { extra: { componentStack: errorInfo.componentStack } });
    this.setState({ errorId });
  }

  render() {
    if (this.state.hasError) {
      return (
        this.props.fallback || (
          <div className="flex min-h-[50vh] flex-col items-center justify-center px-4 text-center">
            <h2 className="mb-2 text-xl font-semibold text-[#0A2540]">Something went wrong</h2>
            <p className="mb-6 text-sm text-[#5A6B7B]">
              We&apos;ve been notified and are working to fix the issue.
            </p>
            <button
              onClick={() => window.location.reload()}
              className="rounded-xl bg-[#0A2540] px-4 py-2 text-sm font-medium text-white hover:bg-[#071b2e]"
            >
              Reload page
            </button>
          </div>
        )
      );
    }

    return this.props.children;
  }
}
