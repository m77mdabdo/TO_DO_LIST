# Todo Board API, version 1

All calls live under `/api/v1` and take a personal API token, made on the
**Integrations** page:

```
Authorization: Bearer tdb_…
```

A token only reaches the boards chosen when it was made, can be `read` or
`write`, expires, can be revoked, and never does more than its owner may do
on that board right now. The session cookie is ignored, so a web page can't
call the API through someone's browser. Request bodies are JSON
(`Content-Type: application/json`); dates are `YYYY-MM-DD`.

## Errors

Every failure has the same shape and an HTTP status that matches it:

```json
{ "ok": false, "error": "invalid", "message": "Due date must be a date.", "fields": { "due_date": "Due date must be a date." } }
```

| status | `error` | meaning |
|---|---|---|
| 401 | `unauthorized` | no token, a wrong one, a revoked or expired one |
| 403 | `forbidden` | a read-only token, or the owner may not do this on the board |
| 404 | `not_found` | the board or card doesn't exist **for this token** (other people's boards look the same as missing ones) |
| 422 | `invalid` | a value was refused; `fields` names each one |
| 429 | `rate_limited` | too many calls; wait `retry_after` seconds |
| 500 | `server_error` | our fault |

## Rate limits

120 calls a minute per token and 300 a minute per IP address. Every answer
carries `X-RateLimit-Limit` and `X-RateLimit-Remaining`; a 429 also carries
`Retry-After` (seconds).

## Paging

Lists of boards and cards take `?limit=` (1–100, default 50) and `?page=`
(from 1) and answer with `page`, `limit`, `total` and `next_page` (`null` on
the last page). Comments come newest first, 20 at a time: pass the
`next_before` value back as `?before=` for older ones.

## Endpoints

| method | path | scope | what it does |
|---|---|---|---|
| GET | `/me` | read | the token's owner and the token itself |
| GET | `/boards` | read | the token's boards with their open lists (paged) |
| GET | `/boards/{board}/lists` | read | the open lists of a board |
| GET | `/boards/{board}/cards` | read | open cards, `?list=` (id or name) to filter (paged) |
| POST | `/boards/{board}/cards` | write | new card: `list` (id or name), `title`, `description`, `start_date`, `due_date` |
| GET | `/boards/{board}/fields` | read | the board's custom fields and their options |
| GET | `/cards/{card}` | read | one card, with its custom field values |
| POST | `/cards/{card}` | write | partial update: only the keys you send change (`title`, `description`, `start_date`, `due_date`; `null` or `""` clears a date) |
| POST | `/cards/{card}/move` | write | `list` (id or name): moves the card to the end of that list |
| POST | `/cards/{card}/complete` | write | `complete` (default `true`; `false` reopens) |
| POST | `/cards/{card}/archive` | write | `archived` (default `true`; `false` restores) |
| GET | `/cards/{card}/comments` | read | comments, newest first |
| POST | `/cards/{card}/comments` | write | `body`: adds a comment as the token's owner |
| POST | `/cards/{card}/fields` | write | `fields`: an object of field id → value (`""` clears) |

`{board}` and `{card}` are the public ids shown in page addresses
(`/boards/01J…`, `/cards/01J…`).

A card looks like this:

```json
{
  "id": "01JABC…", "title": "Ship it", "description": null,
  "list": "Doing", "list_id": 12, "start": "2026-01-25", "due": "2026-02-01",
  "complete": false, "archived": false,
  "labels": [{ "name": "Urgent", "color": "coral" }], "people": ["Mona"],
  "created_at": "2026-01-20 10:00:00"
}
```

## Example

```sh
curl -s -H "Authorization: Bearer $TOKEN" https://todo.example.com/api/v1/boards
curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"list":"Doing"}' https://todo.example.com/api/v1/cards/01JABC…/move
```

Webhooks (signed with HMAC-SHA256, header `X-Todo-Signature`) are set up per
board under **Board menu → Webhooks**.
