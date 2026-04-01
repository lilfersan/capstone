import pandas as pd
import numpy as np
import pickle
from sklearn.model_selection import train_test_split
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score
from sentence_transformers import SentenceTransformer

print("Loading data...")
data = pd.read_csv('clean_fakejobs.csv')
X = data['text']
y = data['fraudulent']
print(f"Data loaded: {len(X)} samples")

print("Splitting data...")
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.3, random_state=42)
print(f"Train: {len(X_train)}, Test: {len(X_test)}")

print("Fitting TF-IDF...")
tfidf = TfidfVectorizer(max_features=5000, stop_words='english')
X_train_tfidf = tfidf.fit_transform(X_train).toarray()
X_test_tfidf = tfidf.transform(X_test).toarray()
print(f"TF-IDF shape: train {X_train_tfidf.shape}")

print("Computing BERT embeddings...")
bert_model = SentenceTransformer('all-MiniLM-L6-v2')
X_train_bert = bert_model.encode(X_train.tolist())
X_test_bert = bert_model.encode(X_test.tolist())
print(f"BERT shape: train {X_train_bert.shape}")

print("Combining features...")
X_train_combined = np.hstack((X_train_tfidf, X_train_bert))
X_test_combined = np.hstack((X_test_tfidf, X_test_bert))
print(f"Combined shape: train {X_train_combined.shape}")

print("Training RandomForest...")
rf = RandomForestClassifier(n_estimators=100, random_state=42, n_jobs=-1)
rf.fit(X_train_combined, y_train)

y_pred = rf.predict(X_test_combined)
acc = accuracy_score(y_test, y_pred)
print(f"Hybrid RF Accuracy: {acc:.4f}")

print("Saving models...")
pickle.dump(tfidf, open('tfidf.pkl', 'wb'))
pickle.dump(rf, open('model.pkl', 'wb'))
print("Saved tfidf.pkl and model.pkl")

print("Done!")
