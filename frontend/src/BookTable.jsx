import { useState } from 'react'
import { STATUSES } from './labels.js'

export default function BookTable({ books, onUpdate, onEdit, onDelete }) {
  if (books.length === 0) {
    return <p className="empty">Pridajte prvú knihu – ručne alebo vyhľadaním v katalógu Open Library.</p>
  }

  return (
    <table className="books">
      <thead>
        <tr>
          <th scope="col">Kniha</th>
          <th scope="col">Rok</th>
          <th scope="col">Žáner</th>
          <th scope="col">Stav</th>
          <th scope="col">Prečítané</th>
          <th scope="col"><span className="sr-only">Akcie</span></th>
        </tr>
      </thead>
      <tbody>
        {books.map((book) => (
          <BookRow key={book.id} book={book} onUpdate={onUpdate} onEdit={onEdit} onDelete={onDelete} />
        ))}
      </tbody>
    </table>
  )
}

function BookRow({ book, onUpdate, onEdit, onDelete }) {
  const [pages, setPages] = useState(String(book.pages_read))
  const [error, setError] = useState('')
  const [syncedFrom, setSyncedFrom] = useState(book.pages_read)

  // Server mohol strany zmeniť (napr. pri stave „prečítaná“), prevezmi novú hodnotu.
  if (syncedFrom !== book.pages_read) {
    setSyncedFrom(book.pages_read)
    setPages(String(book.pages_read))
  }

  async function save(changes) {
    setError('')
    try {
      await onUpdate(book.id, changes)
    } catch (e) {
      setError(e.errors?.pages_read?.[0] || e.message)
      setPages(String(book.pages_read))
    }
  }

  function commitPages() {
    if (pages === String(book.pages_read)) return
    save({ pages_read: Number(pages) })
  }

  return (
    <tr>
      <td data-label="Kniha">
        <span className="book-title">{book.title}</span>
        <span className="book-author">{book.author}</span>
      </td>
      <td data-label="Rok">{book.publish_year}</td>
      <td data-label="Žáner">{book.genre}</td>
      <td data-label="Stav">
        <select
          className={`status status-${book.status}`}
          value={book.status}
          aria-label={`Stav knihy ${book.title}`}
          onChange={(e) => save({ status: e.target.value })}
        >
          {Object.entries(STATUSES).map(([key, label]) => (
            <option key={key} value={key}>{label}</option>
          ))}
        </select>
      </td>
      <td data-label="Prečítané">
        <div className="progress-cell">
          <input
            type="number"
            min="0"
            max={book.pages}
            inputMode="numeric"
            value={pages}
            aria-label={`Prečítané strany knihy ${book.title}`}
            onChange={(e) => setPages(e.target.value)}
            onBlur={commitPages}
            onKeyDown={(e) => e.key === 'Enter' && e.currentTarget.blur()}
          />
          <span className="of">/ {book.pages}</span>
          <span className="percent">{book.percent} %</span>
        </div>
        <div className="bar" role="progressbar" aria-valuenow={book.percent} aria-valuemin="0" aria-valuemax="100" aria-label="Percento dokončenia">
          <span style={{ width: `${book.percent}%` }} />
        </div>
        {error && <p className="field-error" role="alert">{error}</p>}
      </td>
      <td className="actions">
        <button type="button" className="btn btn-quiet" onClick={() => onEdit(book)}>Upraviť</button>
        <button type="button" className="btn btn-quiet btn-danger" onClick={() => onDelete(book)}>Zmazať</button>
      </td>
    </tr>
  )
}
