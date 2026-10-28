// The engine is mounted at console.enhanced-pricing (package.json, fleetbase.route). Transitions
// go through the host router, so they use the full route name.
export const ROOT = 'console.enhanced-pricing';

export function route(name) {
    return `${ROOT}.${name}`;
}
