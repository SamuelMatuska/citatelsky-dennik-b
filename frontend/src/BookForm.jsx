import { useState } from 'react'
import { api } from './api.js'
import { GENRES, STATUSES } from './labels.js'

const EMPTY = { title: '', author: '', publish_year: '', genre: '', pages: '', isbn: '', status: 'unread' }

export default function BookForm({ book, onSaved, onCancel }) {
  const [form, setForm] = useState(book ? pick(book) : EMPTY)
  const [errors, setErrors] = useState({})
  const [message, setMessage] = useState('')
  const [saving, setSaving] = useState(false)

  function set(field, value) {
    setForm((f) => ({ ...f, [field]: value }))
  }

  function prefill(result) {
    setForm((f) => ({
      ...f,
      title: result.title ?? '',
      author: result.author ?? '',
      publish_year: result.publish_year ?? '',
      pages: result.pages ?? '',
      isbn: result.isbn ?? '',
      genre: '', // DNR S.15: žáner musí používateľ doplniť sám
    }))
    setErrors({})
  }

  async function submit(e) {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    setMessage('')
    const body = {
      ...form,
      publish_year: Number(form.publish_year),
      pages: Number(form.pages),
      isbn: form.isbn.trim() || null,
    }
    if (book) delete body.status // stav sa mení priamo v zozname
    try {
      const saved = await api(book ? `/books/${book.id}` : '/books', { method: book ? 'PUT' : 'POST', body })
      onSaved(saved)
    } catch (err) {
      setErrors(err.errors)
      setMessage(err.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <section className="panel" aria-labelledby="form-heading">
      <h2 id="form-heading">{book ? `Upraviť „${book.title}“` : 'Nová kniha'}</h2>

      {!book && <CatalogSearch onPick={prefill} />}

      <form onSubmit={submit} noValidate>
        <div className="grid">
          <Field label="Názov" error={errors.title} wide>
            <input value={form.title} onChange={(e) => set('title', e.target.value)} required />
          </Field>
          <Field label="Autor" error={errors.author} wide>
            <input value={form.author} onChange={(e) => set('author', e.target.value)} required />
          </Field>
          <Field label="Rok vydania" error={errors.publish_year}>
            <input type="number" inputMode="numeric" value={form.publish_year} onChange={(e) => set('publish_year', e.target.value)} required />
          </Field>
          <Field label="Počet strán" error={errors.pages}>
            <input type="number" inputMode="numeric" min="1" value={form.pages} onChange={(e) => set('pages', e.target.value)} required />
          </Field>
          <Field label="Žáner" error={errors.genre}>
            <select value={form.genre} onChange={(e) => set('genre', e.target.value)} required>
              <option value="" disabled>Vyberte žáner</option>
              {GENRES.map((g) => <option key={g} value={g}>{g}</option>)}
            </select>
          </Field>
          <Field label="ISBN (nepovinné)" error={errors.isbn}>
            <input value={form.isbn} onChange={(e) => set('isbn', e.target.value)} />
          </Field>
          {!book && (
            <Field label="Stav" error={errors.status}>
              <select value={form.status} onChange={(e) => set('status', e.target.value)}>
                {Object.entries(STATUSES).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
              </select>
            </Field>
          )}
        </div>

        {message && <p className="alert" role="alert">{message}</p>}

        <div className="form-actions">
          <button type="submit" className="btn btn-primary" disabled={saving}>
            {saving ? 'Ukladám…' : book ? 'Uložiť zmeny' : 'Pridať knihu'}
          </button>
          <button type="button" className="btn btn-quiet" onClick={onCancel}>Zrušiť</button>
        </div>
      </form>
    </section>
  )
}

function CatalogSearch({ onPick }) {
  const [mode, setMode] = useState('title')
  const [query, setQuery] = useState('')
  const [results, setResults] = useState([])
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  async function search(e) {
    e.preventDefault()
    setError('')
    setResults([])
    const q = query.trim()
    if (!q) return
    if (mode === 'isbn' && !/^(\d{9}[\dXx]|\d{13})$/.test(q.replace(/[\s-]/g, ''))) {
      setError('ISBN musí mať 10 alebo 13 číslic.')
      return
    }
    setLoading(true)
    try {
      const data = await api(`/catalog/search?${mode}=${encodeURIComponent(q)}`)
      setResults(data.results)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="catalog">
      <form className="catalog-form" onSubmit={search} role="search">
        <label className="sr-only" htmlFor="catalog-mode">Hľadať podľa</label>
        <select id="catalog-mode" value={mode} onChange={(e) => setMode(e.target.value)}>
          <option value="title">Názov</option>
          <option value="isbn">ISBN</option>
        </select>
        <label className="sr-only" htmlFor="catalog-q">Hľadaný výraz</label>
        <input
          id="catalog-q"
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={mode === 'title' ? 'napr. Malý princ' : 'napr. 9780441172719'}
        />
        <button type="submit" className="btn" disabled={loading}>
          {loading ? 'Hľadám…' : 'Hľadať v Open Library'}
        </button>
      </form>
      {error && <p className="alert" role="alert">{error} Formulár nižšie môžete vyplniť ručne.</p>}
      {results.length > 0 && (
        <ul className="results">
          {results.map((r, i) => (
            <li key={i}>
              <button
                type="button"
                className="result"
                onClick={() => {
                  onPick(r)
                  setResults([])
                }}
              >
                <span className="book-title">{r.title}</span>
                <span className="book-author">
                  {[r.author, r.publish_year, r.pages && `${r.pages} strán`].filter(Boolean).join(', ')}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function Field({ label, error, wide, children }) {
  const message = error?.[0]
  return (
    <label className={`field${wide ? ' field-wide' : ''}${message ? ' has-error' : ''}`}>
      <span className="field-label">{label}</span>
      {children}
      {message && <span className="field-error">{message}</span>}
    </label>
  )
}

function pick(book) {
  return {
    title: book.title,
    author: book.author,
    publish_year: book.publish_year,
    genre: book.genre,
    pages: book.pages,
    isbn: book.isbn ?? '',
    status: book.status,
  }
}
