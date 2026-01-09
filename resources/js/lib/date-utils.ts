import { parseTime } from "@internationalized/date"
import dayjs from "dayjs"
import duration from "dayjs/plugin/duration"
import isBetween from "dayjs/plugin/isBetween"
import relativeTime from "dayjs/plugin/relativeTime"
import timezone from "dayjs/plugin/timezone"
import utc from "dayjs/plugin/utc"

dayjs.extend(utc)
dayjs.extend(timezone)
dayjs.extend(duration)
dayjs.extend(isBetween)
dayjs.extend(relativeTime)

type DateLike = string | number | Date

let localTimeZone: string | undefined

export { dayjs }

export const isValidDate = (date: DateLike) => dayjs(date).isValid()

export function getLocalTimeZone() {
  if (!localTimeZone) {
    localTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone
  }
  return localTimeZone
}

export function safeParseDate(date: any): Date | undefined {
  const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone
  if (date instanceof Date) return dayjs.tz(date, timezone).toDate()
  if (!date || !isValidDate(date)) return undefined

  return dayjs.tz(date, timezone).toDate()
}

export function safeParseTime(time: any) {
  try {
    return parseTime(time)
  } catch {
    return ""
  }
}

export function toLocalDateString(date: DateLike, f = "MM/DD/YYYY, hh:mm A") {
  if (!isValidDate(date)) return ""
  return dayjs(date).tz(getLocalTimeZone()).format(f)
}

/** @deprecated */
export function emailDateFormat(time: any) {
  if (time) {
    return dayjs(time).format("DD/MM/YYYY HH:mm")
  }
  return ""
}

// Standard date display format for the application
export const DATE_DISPLAY_FORMAT = "DD/MM/YYYY"

/**
 * Format a date for display in UI (dd/MM/yyyy)
 */
export function formatDateDisplay(date: DateLike | null | undefined): string {
  if (!date || !isValidDate(date)) return ""
  return dayjs(date).format(DATE_DISPLAY_FORMAT)
}
