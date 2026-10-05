from pydantic import BaseModel

from fastapi import FastAPI
from sentence_transformers import SentenceTransformer

app = FastAPI()

documents = [
    {"id": 1, "text": "Python é uma linguagem de programação versátil, muito usada em ciência de dados e desenvolvimento web."},
    {"id": 2, "text": "FastAPI é um framework moderno e rápido para criar APIs com Python."},
    {"id": 3, "text": "Modelos de linguagem (LLMs) são treinados com grandes volumes de texto para gerar e entender linguagem natural."},
]

model = SentenceTransformer("paraphrase-multilingual-MiniLM-L12-v2")

doc_embeddings = {
    doc["id"]: model.encode(doc["text"], convert_to_tensor=True)
    for doc in documents
}

class QueryRequest(BaseModel):
    query: str 

@app.post("/query")
def query_rag(request: QueryRequest):
    return{"document": documents[0].get("text")}