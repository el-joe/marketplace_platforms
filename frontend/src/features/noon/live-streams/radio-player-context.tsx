'use client';

import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import dynamic from 'next/dynamic';

interface RadioPlayerState {
  /** stream currently loaded in the mini player, null when closed */
  activeId: string | null;
  open: (streamId: string) => void;
  close: () => void;
}

const RadioPlayerContext = createContext<RadioPlayerState>({ activeId: null, open: () => {}, close: () => {} });

const MiniPlayer = dynamic(() => import('./MiniPlayer'), { ssr: false });

export function useRadioPlayer() {
  return useContext(RadioPlayerContext);
}

/**
 * Lives in the storefront layout, which is not remounted on navigation, so the mini player
 * keeps playing while the customer browses the rest of the site.
 */
export default function RadioPlayerProvider({ children }: { children: React.ReactNode }) {
  const [activeId, setActiveId] = useState<string | null>(null);
  const open = useCallback((id: string) => setActiveId(id), []);
  const close = useCallback(() => setActiveId(null), []);
  const value = useMemo(() => ({ activeId, open, close }), [activeId, open, close]);

  return (
    <RadioPlayerContext.Provider value={value}>
      {children}
      {activeId && <MiniPlayer key={activeId} streamId={activeId} onClose={close} />}
    </RadioPlayerContext.Provider>
  );
}
