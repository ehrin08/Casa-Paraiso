FROM python:3.12-slim

WORKDIR /srv
ENV HF_HOME=/models PIP_NO_CACHE_DIR=1

# CPU-only torch keeps the image far smaller than the CUDA build.
RUN pip install torch==2.5.1 --index-url https://download.pytorch.org/whl/cpu
COPY requirements.txt .
RUN pip install -r requirements.txt
COPY app.py .

EXPOSE 8000
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8000"]
