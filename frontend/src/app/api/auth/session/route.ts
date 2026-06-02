import { cookies } from 'next/headers';
import { NextResponse } from 'next/server';

const AUTH_COOKIE = 'bookly_auth_token';
const SESSION_COOKIE = 'bookly_session';

export async function GET() {
  const cookieStore = await cookies();
  const token = cookieStore.get(AUTH_COOKIE)?.value;
  const sessionData = cookieStore.get(SESSION_COOKIE)?.value;

  if (!token || !sessionData) {
    return NextResponse.json({ user: null, token: null }, { status: 401 });
  }

  try {
    const user = JSON.parse(sessionData);
    return NextResponse.json({ user, token });
  } catch {
    return NextResponse.json({ user: null, token: null }, { status: 401 });
  }
}

export async function POST(request: Request) {
  const { user, token } = await request.json();
  const cookieStore = await cookies();

  cookieStore.set(AUTH_COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    path: '/',
    maxAge: 60 * 60 * 24 * 7, // 7 days
  });

  cookieStore.set(SESSION_COOKIE, JSON.stringify(user), {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    path: '/',
    maxAge: 60 * 60 * 24 * 7,
  });

  return NextResponse.json({ success: true });
}

export async function DELETE() {
  const cookieStore = await cookies();
  cookieStore.delete(AUTH_COOKIE);
  cookieStore.delete(SESSION_COOKIE);

  return NextResponse.json({ success: true });
}
