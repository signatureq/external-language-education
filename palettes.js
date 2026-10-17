document.querySelectorAll('.preview-viewport').forEach(viewport => {
  const frame = viewport.querySelector('iframe');
  let height = 9000;
  function fit() {
    const scale = viewport.clientWidth / 1280;
    frame.style.transform = `scale(${scale})`;
    frame.style.height = `${height}px`;
    viewport.style.height = `${Math.ceil(height * scale)}px`;
  }
  async function measure() {
    const doc = frame.contentDocument;
    if (!doc || !doc.querySelector('.world-hero')) return;
    await doc.fonts.ready;
    const images = Array.from(doc.images);
    images.forEach(image => { image.loading = 'eager'; });
    await Promise.all(images.map(image => image.decode().catch(() => {})));
    height = doc.body.offsetHeight;
    fit();
    viewport.dataset.ready = 'true';
  }
  frame.addEventListener('load', measure);
  new ResizeObserver(fit).observe(viewport);
  fit();
  if (frame.contentDocument?.readyState === 'complete') measure();
});
