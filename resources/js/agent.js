const PORTS = [3333, 3334, 3335];
let cachedPort = null;

async function tryPort(port) {
  try {
    const health = await fetch(`http://127.0.0.1:${port}/health`, {
      method: "GET",
    });
    if (!health.ok) return null;
    const data = await health.json();
    return { port, ...data };
  } catch (e) {
    return null;
  }
}

export async function detectAgent() {
  if (cachedPort) {
    const res = await tryPort(cachedPort);
    if (res) return { online: true, port: cachedPort, ...res };
  }

  for (const port of PORTS) {
    const res = await tryPort(port);
    if (res) {
      cachedPort = port;
      return { online: true, port, ...res };
    }
  }
  return { online: false };
}
