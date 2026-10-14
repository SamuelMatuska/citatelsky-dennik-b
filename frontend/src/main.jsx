import { useEffect, useState } from 'react'
import { createRoot } from 'react-dom/client'

function App() {
  const [books, setBooks] = useState([])

  useEffect(() => {
    fetch('/api/books').then((r) => r.json()).then(setBooks)
  }, [])

  return (
    <>
      <h1>Čitateľský denník B</h1>
      <table>
        <thead>
          <tr><th>Názov</th><th>Autor</th><th>Rok</th><th>Žáner</th><th>Strán</th></tr>
        </thead>
        <tbody>
          {books.map((b) => (
            <tr key={b.id}>
              <td>{b.title}</td><td>{b.author}</td><td>{b.publish_year}</td><td>{b.genre}</td><td>{b.pages}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <footer>Laravel · React · MariaDB · Docker</footer>
    </>
  )
}

createRoot(document.getElementById('root')).render(<App />)
