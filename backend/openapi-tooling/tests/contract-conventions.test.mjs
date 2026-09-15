import assert from 'node:assert/strict'
import { test } from 'node:test'

import { validateContractConventions } from '../scripts/check-contract-conventions.mjs'

function fixture() {
  return {
    openapi: '3.1.1',
    info: { title: 'Fixture', version: 'v1.0' },
    paths: {
      '/things': {
        post: {
          operationId: 'createThing',
          requestBody: {
            content: { 'application/json': { schema: { type: 'string', format: 'email' } } },
          },
          responses: {
            200: {
              description: 'OK',
              content: { 'application/json': { schema: { type: 'string', format: 'uuid' } } },
            },
          },
        },
      },
    },
  }
}

test('accepts supported formats and media types', () => {
  assert.deepEqual(validateContractConventions(fixture()), [])
})

test('rejects duplicate operation IDs', () => {
  const specification = fixture()
  specification.paths['/other'] = { get: { ...specification.paths['/things'].post } }

  assert.match(validateContractConventions(specification).join('\n'), /operationId .* duplicates/)
})

test('rejects an unsupported or type-incompatible format', () => {
  const specification = fixture()
  specification.paths['/things'].post.requestBody.content['application/json'].schema = {
    type: 'integer',
    format: 'email',
  }

  assert.match(validateContractConventions(specification).join('\n'), /unsupported format\/type combination/)
})

test('rejects malformed and empty media type maps', () => {
  const specification = fixture()
  specification.paths['/things'].post.requestBody.content = {}
  specification.paths['/things'].post.responses[200].content = {
    'not a media type': { schema: { type: 'string' } },
  }

  const errors = validateContractConventions(specification).join('\n')
  assert.match(errors, /content must be a non-empty media type map/)
  assert.match(errors, /invalid media type/)
})

test('checks path-level parameters and reusable response content', () => {
  const specification = fixture()
  specification.paths['/things'].parameters = [
    { name: 'locale', in: 'query', schema: { type: 'integer', format: 'email' } },
  ]
  specification.components = {
    responses: {
      Export: { description: 'Export', content: { invalid: { schema: { type: 'string' } } } },
    },
  }

  const errors = validateContractConventions(specification).join('\n')
  assert.match(errors, /path parameter.*unsupported format\/type combination/)
  assert.match(errors, /components.responses.Export: invalid media type/)
})
