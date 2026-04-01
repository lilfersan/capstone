import pandas as pd
import numpy as np
import pickle
from sklearn.model_selection import train_test_split
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score

print("Loading data...")
data = pd.read_csv('clean_fakejobs.csv')
X = data['text'].values
y = data['fraudulent'].values
print("Data loaded:", data.shape)

X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.3, random_state=42)
print("Train/Test split:", len(X_train), len(X_test))

print("Fitting TF-IDF...")
tfidf = TfidfVectorizer(max_features=5000, stop_words='english')
X_train_tfidf = tfidf.fit_transform(X_train).toarray()
X_test_tfidf = tfidf.transform(X_test).toarray()
print("TF-IDF shape:", X_train_tfidf.shape)

print("Training RF...")
rf = RandomForestClassifier(n_estimators=100, random_state=42, n_jobs=-1)
rf.fit(X_train_tfidf, y_train)

y_pred = rf.predict(X_test_tfidf)
print("Test accuracy:", accuracy_score(y_test, y_pred))

print("Saving...")
pickle.dump(tfidf, open('tfidf.pkl', 'wb'))
pickle.dump(rf, open('model.pkl', 'wb'))
print("Saved tfidf.pkl and model.pkl")
print("TF-IDF only training complete!")
