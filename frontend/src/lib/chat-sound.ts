let audioContext: AudioContext | null = null;

/** Short two-note "pop" played when a chat message arrives. Silent if the browser blocks audio. */
export function playChatSound() {
  if (typeof window === "undefined") return;
  try {
    audioContext ??= new AudioContext();
    const ctx = audioContext;
    if (ctx.state === "suspended") void ctx.resume();

    const now = ctx.currentTime;
    [
      { freq: 660, start: 0 },
      { freq: 990, start: 0.09 },
    ].forEach(({ freq, start }) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = "sine";
      osc.frequency.value = freq;
      gain.gain.setValueAtTime(0.0001, now + start);
      gain.gain.exponentialRampToValueAtTime(0.2, now + start + 0.015);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + start + 0.18);
      osc.connect(gain).connect(ctx.destination);
      osc.start(now + start);
      osc.stop(now + start + 0.2);
    });
  } catch {
    // autoplay policy or no Web Audio support — sound is optional
  }
}
