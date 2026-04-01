import pandas as pd
import numpy as np
import pickle
from sklearn.model_selection import train_test_split
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics import accuracy_score
from sklearn.ensemble import RandomForestClassifier
from sentence_transformers import SentenceTransformer
import numpy as np

data = pd.read_csv('clean_fakejobs.csv')

print("Data loaded:", data.shape)
X = data['text'].values
y = data['fraudulent'].values

# Splitting dataset in train and test
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.3, random_state=42)
print("Train/Test split:", len(X_train), len(X_test))

print("Loading BERT model...")
bert_model = SentenceTransformer('all-MiniLM-L6-v2')

print("Computing TF-IDF...")
tfidf = TfidfVectorizer(max_features=5000, stop_words='english')
X_train_tfidf = tfidf.fit_transform(X_train).toarray()
X_test_tfidf = tfidf.transform(X_test).toarray()
print("TF-IDF shape:", X_train_tfidf.shape)

print("Computing BERT embeddings...")
X_train_bert = bert_model.encode(X_train)
X_test_bert = bert_model.encode(X_test)
print("BERT shape:", X_train_bert.shape)

print("Combining features...")
X_train_combined = np.hstack((X_train_tfidf, X_train_bert))
X_test_combined = np.hstack((X_test_tfidf, X_test_bert))
print("Combined shape:", X_train_combined.shape)

print("Training RF...")
rf = RandomForestClassifier(n_estimators=100, random_state=42, n_jobs=-1)
rf.fit(X_train_combined, y_train)

y_pred = rf.predict(X_test_combined)
print("Test accuracy:", accuracy_score(y_test, y_pred))

print("Saving...")
pickle.dump(tfidf, open('tfidf.pkl', 'wb'))
pickle.dump(rf, open('model.pkl', 'wb'))
print("Saved tfidf.pkl and model.pkl")
print("Hybrid training complete!")
