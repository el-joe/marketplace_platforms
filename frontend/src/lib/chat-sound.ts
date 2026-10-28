let audioContext: AudioContext | null = null;

function getContext(): AudioContext | null {
  try {
    audioContext ??= new AudioContext();
    if (audioContext.state === "suspended") void audioContext.resume();
    return audioContext;
  } catch {
    return null;
  }
}

// Browsers keep audio locked until a user gesture, so unlock on first interaction.
if (typeof window !== "undefined") {
  ["pointerdown", "keydown", "touchstart"].forEach((event) =>
    window.addEventListener(event, getContext, { once: true, passive: true }),
  );
}

/** Short two-note "pop" played when a chat message arrives. Silent if the browser blocks audio. */
export function playChatSound() {
  if (typeof window === "undefined") return;
  try {
    const ctx = getContext();
    // still locked (no interaction yet): skip rather than play late
    if (!ctx || ctx.state !== "running") return;

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
