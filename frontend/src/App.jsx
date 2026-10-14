import { useEffect, useState } from 'react'
import { api } from './api.js'
import Shelf from './Shelf.jsx'
import BookForm from './BookForm.jsx'
import BookTable from './BookTable.jsx'

export default function App() {
  const [books, setBooks] = useState(null)
  const [loadError, setLoadError] = useState('')
  const [editing, setEditing] = useState(null) // null = zavretý formulár, {} = nová kniha, kniha = úprava

  useEffect(() => {
    api('/books').then(setBooks).catch((e) => setLoadError(e.message))
  }, [])

  function replaceBook(book) {
    setBooks((list) => {
      const rest = list.filter((b) => b.id !== book.id)
      return [book, ...rest]
    })
  }

  async function updateBook(id, changes) {
    const book = await api(`/books/${id}`, { method: 'PUT', body: changes })
    setBooks((list) => list.map((b) => (b.id === id ? book : b)))
    return book
  }

  async function deleteBook(book) {
    if (!window.confirm(`Naozaj zmazať knihu „${book.title}“?`)) return
    await api(`/books/${book.id}`, { method: 'DELETE' })
    setBooks((list) => list.filter((b) => b.id !== book.id))
  }

  return (
    <div className="page">
      <header className="masthead">
        <div className="masthead-text">
          <h1>Čitateľský denník B</h1>
          <p className="lede">
            {books ? summary(books) : 'Načítavam knihy…'}
          </p>
        </div>
        {!editing && (
          <button type="button" className="btn btn-primary" onClick={() => setEditing({})}>
            Pridať knihu
          </button>
        )}
      </header>

      {books && books.length > 0 && <Shelf books={books} />}

      {editing && (
        <BookForm
          key={editing.id ?? 'new'}
          book={editing.id ? editing : null}
          onSaved={(book) => {
            replaceBook(book)
            setEditing(null)
          }}
          onCancel={() => setEditing(null)}
        />
      )}

      <main>
        {loadError && <p className="alert" role="alert">{loadError}</p>}
        {books && (
          <BookTable books={books} onUpdate={updateBook} onEdit={setEditing} onDelete={deleteBook} />
        )}
      </main>

      <footer className="footer">Laravel · React · MariaDB · Docker</footer>
    </div>
  )
}

function summary(books) {
  if (books.length === 0) return 'Zatiaľ tu nie je žiadna kniha.'
  const reading = books.filter((b) => b.status === 'reading').length
  const read = books.filter((b) => b.status === 'read').length
  return `${books.length} ${plural(books.length, 'kniha', 'knihy', 'kníh')} v denníku, ${reading} práve čítate, ${read} máte prečítané.`
}

function plural(n, one, few, many) {
  if (n === 1) return one
  if (n >= 2 && n <= 4) return few
  return many
}
