import { STATUSES } from './labels.js'

// Polica: každá kniha je chrbát. Šírka podľa počtu strán, výplň podľa prečítaných strán.
export default function Shelf({ books }) {
  const maxPages = Math.max(...books.map((b) => b.pages))

  return (
    <figure className="shelf" aria-label="Polica s knihami">
      <div className="shelf-row">
        {books.map((b) => (
          <div
            key={b.id}
            className={`spine spine-${b.status}`}
            style={{ '--w': `${28 + (b.pages / maxPages) * 34}px`, '--fill': `${b.percent}%` }}
            title={`${b.title} – ${STATUSES[b.status]}, ${b.percent} %`}
          >
            <span className="spine-title">{b.title}</span>
          </div>
        ))}
      </div>
      <figcaption className="shelf-legend">
        {Object.entries(STATUSES).map(([key, label]) => (
          <span key={key} className="legend-item">
            <span className={`legend-dot spine-${key}`} aria-hidden="true" />
            {label}
          </span>
        ))}
      </figcaption>
    </figure>
  )
}
