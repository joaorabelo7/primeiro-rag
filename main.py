import os
from pydantic import BaseModel
import openai
from fastapi import FastAPI, HTTPException
from sentence_transformers import SentenceTransformer, util
from openai import OpenAI

openai_client = OpenAI(
    base_url="http://localhost:11434/v1",
    api_key="ollama",  # qualquer texto, o Ollama ignora
)

app = FastAPI()

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost", "http://127.0.0.1"],
    allow_methods=["*"],
    allow_headers=["*"],
)

documents = [
    {"id": 1, "text": "Quem domina o mundo é o Ryan macaco"},
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
    query_embedding = model.encode(request.query, convert_to_tensor=True)
    best_doc = {}
    best_score = float("-inf")

    #Buscar doc mais proximo do query
    for doc in documents:
        score = util.cos_sim(query_embedding, doc_embeddings[doc["id"]])
        if score > best_score:
            best_score = score
            best_doc = doc

    #Enviar doc e prompt para o gpt
    prompt = f"You are an AI assitant. Answer base only on this document: {best_doc['text']}\n\nUser: {request.query}\nAssistant:. if you dont know the answer. return literally this 'Não temos esse dado entre em contato no email'"

    try:
        res = openai_client.chat.completions.create(
    model="llama3.2",
    messages=[{"role": "user", "content": prompt}],
)
        return{"response": res.choices[0].message.content}

    except Exception as e: 
        raise HTTPException(status_code=500, detail=str(e))