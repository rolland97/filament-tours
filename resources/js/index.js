import { driver } from 'driver.js'
import 'driver.js/dist/driver.css'

/**
 * The second of the two escaping guards, and the load-bearing one.
 *
 * driver.js assigns popover copy with innerHTML, so a string reaching it as
 * markup becomes real DOM — `<img src=x onerror=...>` executes. Server-side
 * escaping only protects the transport into the page; it is undone the moment
 * JSON.parse hands JS the original characters back.
 *
 * Escaping here means driver's innerHTML renders the copy as visible text.
 * onPopoverRender is NOT a substitute: it runs after the assignment, by which
 * point an onerror payload has already fired.
 */
function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;')
}

/**
 * The client half of the package: pick one tour, drop the steps that are not
 * on the page, and drive it.
 */
export default function filamentTours(payload = {}) {
    return {
        panel: payload.panel ?? '',
        debug: payload.debug ?? false,
        seenEndpoint: payload.seenEndpoint ?? null,
        tours: payload.tours ?? [],

        instance: null,

        init() {
            const tour = this.tours.find((candidate) => this.isEligible(candidate))

            if (tour) {
                this.start(tour)
            }
        },

        /**
         * Eligible = survived server-side resolution, has at least one step
         * present in the DOM, and — if run-once — has not been seen.
         *
         * Under a server driver, seen tours were already filtered out before
         * render, so the localStorage check only applies to the local driver.
         */
        isEligible(tour) {
            if (tour.once && this.seenEndpoint === null && this.hasSeenLocally(tour.id)) {
                return false
            }

            return this.resolveSteps(tour).length > 0
        },

        /**
         * A step whose target is missing is skipped, never fatal — users must
         * not meet a broken tour because someone moved a button.
         */
        resolveSteps(tour) {
            return (tour.steps ?? [])
                .filter((step) => {
                    if (document.querySelector(step.selector)) {
                        return true
                    }

                    this.warn(`tour "${tour.id}": no element matches "${step.selector}", skipping step`)

                    return false
                })
                .map((step) => ({
                    element: step.selector,
                    popover: {
                        // Escaped, never raw: see escapeHtml() above.
                        title: step.title == null ? undefined : escapeHtml(step.title),
                        description: step.body == null ? undefined : escapeHtml(step.body),
                        side: step.side ?? undefined,
                        align: step.align ?? undefined,
                    },
                }))
        },

        start(tour) {
            const steps = this.resolveSteps(tour)

            if (steps.length === 0) {
                return
            }

            this.destroy()

            this.instance = driver({
                steps,
                onDestroyed: () => {
                    // Finish and dismiss are the same decision: the user is done.
                    this.markSeen(tour)
                    this.instance = null
                },
            })

            this.instance.drive()
        },

        destroy() {
            if (this.instance) {
                this.instance.destroy()
                this.instance = null
            }
        },

        markSeen(tour) {
            if (! tour.once) {
                return
            }

            if (this.seenEndpoint === null) {
                this.rememberLocally(tour.id)
            }
        },

        seenKey(tourId) {
            return `filament-tours:${this.panel}:${tourId}`
        },

        hasSeenLocally(tourId) {
            try {
                return window.localStorage.getItem(this.seenKey(tourId)) !== null
            } catch (error) {
                // Private browsing and blocked storage both throw. A tour that
                // replays is better than one that crashes the page.
                return false
            }
        },

        rememberLocally(tourId) {
            try {
                window.localStorage.setItem(this.seenKey(tourId), '1')
            } catch (error) {
                this.warn(`tour "${tourId}": could not write localStorage`)
            }
        },

        warn(message) {
            if (this.debug) {
                console.warn(`[filament-tours] ${message}`)
            }
        },
    }
}
