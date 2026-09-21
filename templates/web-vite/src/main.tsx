import React from "react";
import { createRoot } from "react-dom/client";

export default function App() {
  return <main style={{ fontFamily: "system-ui", padding: "3rem" }}><h1>Nowy projekt</h1><p>Projekt jest gotowy do realizacji.</p></main>;
}

createRoot(document.getElementById("root")!).render(<React.StrictMode><App /></React.StrictMode>);
