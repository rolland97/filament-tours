import { driver } from 'driver.js'
import 'driver.js/dist/driver.css'

export default function filamentTours(payload = {}) {
    return {
        payload,
        driver: null,

        init() {
            const steps = this.payload.steps ?? []

            if (steps.length === 0) {
                return
            }

            this.driver = driver({ steps })
            this.driver.drive()
        },
    }
}
