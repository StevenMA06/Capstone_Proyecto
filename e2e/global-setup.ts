import { startServer } from '../scripts/e2e/server.mjs';

export default async function globalSetup() {
    return await startServer();
}
