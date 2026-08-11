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
        teardown: null,
        onStartRequest: null,
        suppressSeen: false,
        // Tours whose seen-write failed. In memory only: this page session, no
        // retry, and the server is authoritative again on the next load.
        suppressed: [],

        init() {
            // Filament panels navigate as an SPA. Without this, an overlay
            // describing the page you left survives onto the page you arrived
            // at, pointing at elements that are gone — or worse, at different
            // elements that happen to match the same selector.
            this.teardown = () => this.stopTour()
            document.addEventListener('livewire:navigating', this.teardown)

            // Replay, from StartTourAction or from any Alpine/Livewire context.
            this.onStartRequest = (event) => this.startById(event?.detail?.tour)
            window.addEventListener('filament-tours:start', this.onStartRequest)

            // Resolve each candidate at most once. Calling resolveSteps() from
            // both the eligibility check and start() warned twice per missing
            // selector, which reads like two separate faults.
            for (const tour of this.tours) {
                if (this.isSeen(tour)) {
                    continue
                }

                const steps = this.resolveSteps(tour)

                if (steps.length > 0) {
                    this.start(tour, steps)

                    break
                }
            }
        },

        /**
         * Alpine's own teardown hook. Named by Alpine, not by us — it fires when
         * the component is removed, which is also a moment no tour should outlive.
         */
        destroy() {
            document.removeEventListener('livewire:navigating', this.teardown)
            window.removeEventListener('filament-tours:start', this.onStartRequest)
            this.stopTour()
        },

        /**
         * Run a tour by id, on demand.
         *
         * Ignores seen state entirely — replay is the whole point, and a user
         * who asked for the tour has overridden whatever "seen" said. Finishing
         * a replay does not re-mark it either: it is already seen.
         */
        startById(tourId) {
            if (! tourId) {
                return
            }

            const tour = this.tours.find((candidate) => candidate.id === tourId)

            if (! tour) {
                // Nothing starts and the user sees no error. A replay control
                // pointing at a tour that does not apply here is a developer
                // mistake, not something to interrupt a user over.
                this.warn(`tour "${tourId}": not available on this page, ignoring start request`)

                return
            }

            this.start(tour, null, { replay: true })
        },

        /**
         * Under a server driver, seen tours were already filtered out before
         * render, so this localStorage check only bites under the local one.
         */
        isSeen(tour) {
            if (tour.once !== true) {
                return false
            }

            if (this.suppressed.includes(tour.id)) {
                return true
            }

            // Under a server driver, seen tours were already filtered out
            // before render, so localStorage only decides for the local one.
            return this.seenEndpoint === null && this.hasSeenLocally(tour.id)
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

        start(tour, resolved = null, { replay = false } = {}) {
            const steps = resolved ?? this.resolveSteps(tour)

            if (steps.length === 0) {
                return
            }

            this.stopTour()

            this.instance = driver({
                steps,
                onDestroyed: () => {
                    // Finish and dismiss are the same decision: the user is done.
                    // Navigating away is NOT — they neither finished nor
                    // dismissed it, so a run-once tour must still be offered
                    // next visit (FR-012).
                    // A replay is already seen; re-recording it is a wasted
                    // write, and under a server driver a wasted request.
                    if (! this.suppressSeen && ! replay) {
                        this.markSeen(tour)
                    }

                    this.instance = null
                },
            })

            this.instance.drive()
        },

        /**
         * Tear down any live tour. Deliberately NOT called destroy(): that name
         * belongs to Alpine's lifecycle hook above, and having both meant the
         * framework silently calling ours.
         */
        stopTour() {
            if (this.instance) {
                // Tell onDestroyed this is our teardown, not the user finishing.
                this.suppressSeen = true
                this.instance.destroy()
                this.suppressSeen = false
                this.instance = null
            }
        },

        markSeen(tour) {
            if (! tour.once) {
                return
            }

            if (this.seenEndpoint === null) {
                this.rememberLocally(tour.id)

                return
            }

            this.postSeen(tour)
        },

        /**
         * Tell the server this tour is done.
         *
         * Fails open, deliberately. If the write does not land, the tour may
         * run once more on the next page load — visible and self-correcting.
         * Failing closed would silently retire a tour after one transient blip,
         * and the user would have no way to know.
         */
        postSeen(tour) {
            const url = this.seenEndpoint.replace('__TOUR__', encodeURIComponent(tour.id))

            const headers = { Accept: 'application/json' }
            const token = document.querySelector('meta[name="csrf-token"]')?.content

            if (token) {
                headers['X-CSRF-TOKEN'] = token
            }

            fetch(url, { method: 'POST', headers, credentials: 'same-origin' })
                .then((response) => {
                    if (! response.ok) {
                        this.failOpen(tour, `server responded ${response.status}`)
                    }
                })
                // No retry: the user is likely navigating away, and queueing
                // work in a page that is about to be discarded helps nobody.
                .catch(() => this.failOpen(tour, 'request did not complete'))
        },

        failOpen(tour, reason) {
            this.suppressed.push(tour.id)

            this.warn(`tour "${tour.id}": could not record as seen (${reason}); it may run again next load`)
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
