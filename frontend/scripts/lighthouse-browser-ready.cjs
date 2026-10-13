// LHCI invokes this before each URL using the same browser as its real audits.
// Prepare only about:blank; do not visit the app or retain application storage.
module.exports = async (browser) => {
  const { prepareBrowserCpu } = await import('./lighthouse-runtime-preflight.mjs');
  await prepareBrowserCpu(browser);
};
