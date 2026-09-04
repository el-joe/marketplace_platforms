'use client';

import { useEffect } from 'react';
import { initEcho } from '@/src/lib/echo';

export default function EchoProvider({ children }: { children: React.ReactNode }) {
  useEffect(() => {
    initEcho();
  }, []);

  return <>{children}</>;
}
