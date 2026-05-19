import * as Sentry from '@sentry/nextjs';

Sentry.init({
  dsn: process.env.NEXT_PUBLIC_SENTRY_DSN,
  environment: process.env.NEXT_PUBLIC_APP_ENV || 'development',
  beforeSend(event) {
    // Strip PII from events per FR-024
    if (event.user) {
      delete event.user.ip_address;
    }
    if (event.request && event.request.headers) {
      const headers = event.request.headers as Record<string, string>;
      delete headers['Authorization'];
    }
    if (event.exception && event.exception.values) {
      event.exception.values.forEach((value) => {
        if (value.stacktrace && value.stacktrace.frames) {
          value.stacktrace.frames.forEach((frame) => {
            if (frame.vars) {
              delete frame.vars.password;
              delete frame.vars.token;
              delete frame.vars.cardNumber;
              delete frame.vars.cvc;
            }
          });
        }
      });
    }
    return event;
  },
});
