from fastapi import FastAPI, HTTPException
from database import get_db

app = FastAPI()

@app.get("/")
async def root():
    return {"message": "Aesthetic AI Backend is Live"}

@app.post("/save-design")
async def save_design(user_id: str, prompt: str, image_url: str):
    conn = get_db()
    if not conn:
        raise HTTPException(status_code=500, detail="Database connection failed")
    
    try:
        with conn:
            with conn.cursor() as cur:
                # Ensure the 'designs' table exists in your Neon database
                query = "INSERT INTO designs (user_id, prompt, image_url) VALUES (%s, %s, %s)"
                cur.execute(query, (user_id, prompt, image_url))
        return {"status": "success", "message": "Design saved to Neon!"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Query failed: {str(e)}")
    finally:
        conn.close()