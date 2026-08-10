import { driver } from 'driver.js'
import 'driver.js/dist/driver.css'

export default function filamentTours(payload = {}) {
    return {
        payload,
        driver: null,

        init() {
            this.driver = driver()
        },
    }
}
