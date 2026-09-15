import { readFile } from 'node:fs/promises'
import { pathToFileURL } from 'node:url'

const methods = new Set(['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'])
const stringFormats = new Set([
  'binary',
  'byte',
  'date',
  'date-time',
  'duration',
  'email',
  'hostname',
  'idn-email',
  'idn-hostname',
  'ipv4',
  'ipv6',
  'iri',
  'iri-reference',
  'json-pointer',
  'password',
  'regex',
  'relative-json-pointer',
  'time',
  'uri',
  'uri-reference',
  'uri-template',
  'uuid',
])
const numericFormats = new Set(['double', 'float'])
const integerFormats = new Set(['int32', 'int64'])
const mediaTypePattern = /^(application|audio|font|image|message|model|multipart|text|video)\/[!#$%&'*+.^_`|~0-9A-Za-z-]+(?:\+[!#$%&'*+.^_`|~0-9A-Za-z-]+)?$/

function schemaTypes(schema) {
  if (!Object.hasOwn(schema, 'type')) return []
  return Array.isArray(schema.type) ? schema.type : [schema.type]
}

function inspectSchema(schema, location, errors, seen) {
  if (typeof schema !== 'object' || schema === null || seen.has(schema)) return
  seen.add(schema)

  if (schema.format !== undefined) {
    const types = schemaTypes(schema)
    const valid =
      (stringFormats.has(schema.format) && types.includes('string')) ||
      (numericFormats.has(schema.format) && types.some((type) => type === 'number' || type === 'integer')) ||
      (integerFormats.has(schema.format) && types.includes('integer'))

    if (!valid) {
      errors.push(`${location}: unsupported format/type combination ${JSON.stringify(schema.format)} for ${JSON.stringify(types)}.`)
    }
  }

  for (const [key, value] of Object.entries(schema)) {
    if (key === 'example' || key === 'examples' || key === 'default' || key === 'enum' || key === 'const') continue
    if (Array.isArray(value)) {
      value.forEach((item, index) => inspectSchema(item, `${location}.${key}[${index}]`, errors, seen))
    } else {
      inspectSchema(value, `${location}.${key}`, errors, seen)
    }
  }
}

function inspectContent(content, location, errors, seen) {
  if (typeof content !== 'object' || content === null || Array.isArray(content) || Object.keys(content).length === 0) {
    errors.push(`${location}: content must be a non-empty media type map.`)
    return
  }

  for (const [mediaType, media] of Object.entries(content)) {
    if (!mediaTypePattern.test(mediaType)) errors.push(`${location}: invalid media type ${JSON.stringify(mediaType)}.`)
    if (typeof media !== 'object' || media === null || Array.isArray(media)) {
      errors.push(`${location} ${mediaType}: media type entry must be an object.`)
      continue
    }
    if (media.schema !== undefined) inspectSchema(media.schema, `${location} ${mediaType} schema`, errors, seen)
  }
}

function inspectParameter(parameter, location, errors, seen) {
  if (typeof parameter !== 'object' || parameter === null || Array.isArray(parameter)) return
  if (parameter.schema !== undefined) inspectSchema(parameter.schema, `${location} schema`, errors, seen)
  if (parameter.content !== undefined) inspectContent(parameter.content, location, errors, seen)
}

function inspectResponse(response, location, errors, seen) {
  if (typeof response !== 'object' || response === null || Array.isArray(response)) return
  if (response.content !== undefined) inspectContent(response.content, location, errors, seen)
  for (const [header, value] of Object.entries(response.headers ?? {})) {
    inspectParameter(value, `${location} header ${header}`, errors, seen)
  }
}

export function validateContractConventions(specification) {
  const errors = []
  const seen = new WeakSet()
  const operationIds = new Map()

  for (const [path, pathItem] of Object.entries(specification.paths ?? {})) {
    if (typeof pathItem !== 'object' || pathItem === null) continue
    for (const [index, parameter] of (pathItem.parameters ?? []).entries()) {
      inspectParameter(parameter, `${path} path parameter[${index}]`, errors, seen)
    }
    for (const [method, operation] of Object.entries(pathItem)) {
      if (!methods.has(method) || typeof operation !== 'object' || operation === null) continue
      const location = `${method.toUpperCase()} ${path}`
      if (typeof operation.operationId !== 'string' || operation.operationId.length === 0) {
        errors.push(`${location}: operationId is required.`)
      } else if (operationIds.has(operation.operationId)) {
        errors.push(`${location}: operationId ${JSON.stringify(operation.operationId)} duplicates ${operationIds.get(operation.operationId)}.`)
      } else {
        operationIds.set(operation.operationId, location)
      }

      for (const [index, parameter] of (operation.parameters ?? []).entries()) {
        inspectParameter(parameter, `${location} parameter[${index}]`, errors, seen)
      }
      if (operation.requestBody?.content !== undefined) inspectContent(operation.requestBody.content, `${location} request body`, errors, seen)
      for (const [status, response] of Object.entries(operation.responses ?? {})) {
        inspectResponse(response, `${location} response ${status}`, errors, seen)
      }
    }
  }

  inspectSchema(specification.components?.schemas, 'components.schemas', errors, seen)
  for (const [name, parameter] of Object.entries(specification.components?.parameters ?? {})) {
    inspectParameter(parameter, `components.parameters.${name}`, errors, seen)
  }
  for (const [name, header] of Object.entries(specification.components?.headers ?? {})) {
    inspectParameter(header, `components.headers.${name}`, errors, seen)
  }
  for (const [name, requestBody] of Object.entries(specification.components?.requestBodies ?? {})) {
    if (requestBody.content !== undefined) inspectContent(requestBody.content, `components.requestBodies.${name}`, errors, seen)
  }
  for (const [name, response] of Object.entries(specification.components?.responses ?? {})) {
    inspectResponse(response, `components.responses.${name}`, errors, seen)
  }

  return errors
}

async function main(path) {
  const specification = JSON.parse(await readFile(path, 'utf8'))
  const errors = validateContractConventions(specification)
  if (errors.length > 0) {
    for (const error of errors) process.stderr.write(`- ${error}\n`)
    process.exitCode = 1
  } else {
    process.stdout.write('OpenAPI project conventions are valid.\n')
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  await main(process.argv[2])
}
