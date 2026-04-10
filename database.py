import os
import psycopg2
from psycopg2.extras import RealDictCursor
from dotenv import load_dotenv

# Load environment variables from .env locally
load_dotenv()

DATABASE_URL = os.environ.get("DATABASE_URL")

def get_db():
    """
    Creates and returns a connection to the PostgreSQL database.
    Note: Neon requires SSL, which is handled by the connection string.
    """
    try:
        if not DATABASE_URL:
            raise ValueError("DATABASE_URL environment variable is not set")
        conn = psycopg2.connect(DATABASE_URL, cursor_factory=RealDictCursor)
        return conn
    except Exception as e:
        print(f"Error connecting to database: {e}")
        return None