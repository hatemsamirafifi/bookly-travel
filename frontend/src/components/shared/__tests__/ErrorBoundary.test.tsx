import { render, screen, waitFor } from '@testing-library/react';
import type ErrorBoundary from '../ErrorBoundary';

describe('ErrorBoundary telemetry loading', () => {
  let Boundary: typeof ErrorBoundary;
  const sdkLoaded = jest.fn();
  const captureException = jest.fn(() => 'render-error-id');
  const renderError = new Error('Child render failed');
  let consoleError: jest.SpyInstance;

  beforeEach(() => {
    jest.resetModules();
    sdkLoaded.mockClear();
    captureException.mockClear();
    consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
    jest.doMock('@sentry/nextjs', () => {
      sdkLoaded();
      return { captureException };
    });
    Boundary = jest.requireActual<typeof import('../ErrorBoundary')>('../ErrorBoundary').default;
  });

  afterEach(() => {
    consoleError.mockRestore();
  });

  function ThrowingChild(): never {
    throw renderError;
  }

  it('renders healthy children without loading error telemetry', () => {
    render(<Boundary><p>Healthy content</p></Boundary>);
    expect(screen.getByText('Healthy content')).toBeVisible();
    expect(sdkLoaded).not.toHaveBeenCalled();
    expect(consoleError).not.toHaveBeenCalled();
  });

  it('shows the fallback immediately and reports the original error and component stack', async () => {
    render(<Boundary fallback={<p role="alert">Localized fallback</p>}><ThrowingChild /></Boundary>);
    expect(screen.getByRole('alert')).toHaveTextContent('Localized fallback');
    await waitFor(() => expect(captureException).toHaveBeenCalledTimes(1));
    expect(captureException).toHaveBeenCalledWith(renderError, {
      extra: { componentStack: expect.stringMatching(/\S/) },
    });
  });

  it('keeps the fallback visible when the telemetry module cannot load', async () => {
    const reportingError = new Error('Telemetry module unavailable');
    jest.resetModules();
    jest.doMock('@sentry/nextjs', () => { throw reportingError; });
    const OfflineBoundary = jest.requireActual<typeof import('../ErrorBoundary')>('../ErrorBoundary').default;

    render(<OfflineBoundary fallback={<p role="alert">Localized fallback</p>}><ThrowingChild /></OfflineBoundary>);
    expect(screen.getByRole('alert')).toHaveTextContent('Localized fallback');
    await waitFor(() => expect(consoleError).toHaveBeenCalledWith(
      'Unable to report rendering error', reportingError,
    ));
    expect(screen.getByRole('alert')).toHaveTextContent('Localized fallback');
  });
});
