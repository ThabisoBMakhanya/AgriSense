export default async function smsScheduler(request) {
  if (request.method !== 'POST') {
    return Response.json({ error: 'Method not allowed' }, { status: 405 });
  }

  // Neon strips client-supplied X-Neon headers, so this identifies a trigger invocation.
  const invocationId = request.headers.get('x-neon-trigger-invocation-id');
  if (!invocationId) {
    return Response.json({ error: 'Expected a Neon scheduled trigger' }, { status: 403 });
  }

  const target = process.env.SMS_CRON_TARGET_URL;
  const secret = process.env.SMS_CRON_SECRET;
  if (!target || !secret) {
    console.error('SMS schedule is missing its target URL or shared secret.');
    return Response.json({ error: 'SMS schedule is not configured' }, { status: 503 });
  }

  let scheduledAt = null;
  try {
    const payload = await request.json();
    scheduledAt = payload?.data?.scheduled_at ?? null;
  } catch {
    return Response.json({ error: 'Invalid trigger payload' }, { status: 400 });
  }

  try {
    const response = await fetch(target, {
      method: 'GET',
      headers: { authorization: `Bearer ${secret}` },
      signal: AbortSignal.timeout(27_000),
      cache: 'no-store',
    });
    const detail = (await response.text()).slice(0, 2500);
    console.log(`SMS job ${scheduledAt ?? invocationId}: HTTP ${response.status}`);
    return Response.json(
      { ok: response.ok, scheduled_at: scheduledAt, target_status: response.status, detail },
      { status: response.ok ? 200 : 502 },
    );
  } catch (error) {
    console.error(`SMS job ${scheduledAt ?? invocationId} could not reach the app:`, error);
    return Response.json({ error: 'Could not reach the agriSense SMS job' }, { status: 502 });
  }
}
