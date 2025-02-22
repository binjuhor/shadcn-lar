import { match } from "ts-pattern"
import { z } from "zod"

const _emptyStringToUndefined = z.literal("").transform(() => undefined)

export const asOptional = <T>(schema: z.ZodType<T>) =>
  schema.optional().or(_emptyStringToUndefined)

export const zodErrorMap: z.ZodErrorMap = (
  issue: z.ZodIssueOptionalMessage,
  ctx: z.ErrorMapCtx
) => {
  const NAN: string = "nan"
  const NULL: string = "null"
  const UNDEFINED: string = "undefined"
  const REQUIRED_ERROR_MESSAGE: string = "This field is required."

  let message = match(issue)
    .with({ code: "invalid_enum_value" }, (issue) => {
      if (!issue.received) {
        return REQUIRED_ERROR_MESSAGE
      }
    })
    .with({ code: "invalid_string" }, (issue) => {
      if (issue.validation === "email") {
        return "Please input a valid email address."
      }

      if (issue.validation === "url") {
        return "Please input a valid URL."
      }
    })
    .with({ code: "invalid_type" }, (issue) => {
      if (
        issue.received === NAN ||
        issue.received === NULL ||
        issue.received === UNDEFINED
      ) {
        return REQUIRED_ERROR_MESSAGE
      }
    })
    .with({ code: "too_small" }, () => {
      if (!ctx.data || !ctx.data.length) {
        return REQUIRED_ERROR_MESSAGE
      }
    })
    .otherwise(() => {
      return ctx.defaultError
    })

  message = message || ctx.defaultError
  message = message.endsWith(".") ? message : `${message}.`

  return {
    message,
  }
}

export { z }
