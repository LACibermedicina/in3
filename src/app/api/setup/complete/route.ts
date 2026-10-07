import { NextResponse } from 'next/server';
import { db, persist } from '@/lib/store';

export const runtime = 'nodejs';

export async function POST() {
  const store = db();
  store.settings.onboardingComplete = true;
  persist();
  return NextResponse.json({ ok: true });
}
