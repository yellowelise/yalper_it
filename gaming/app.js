

class CircularBuffer {
  constructor(size) {
    this.buffer = new Array(size);
    this.size = size;
    this.head = 0;
    this.tail = 0;
    this.count = 0;  // numero di elementi attualmente nel buffer
  }

  push(element) {
    this.buffer[this.head] = element;
    this.head = (this.head + 1) % this.size;
    
    if (this.count < this.size) {
      this.count++;
    } else {
      this.tail = (this.tail + 1) % this.size; // sposta tail se il buffer è pieno
    }
  }

  // Accesso tramite indice relativo (0 è l'elemento più vecchio)
  get(index) {
    if (index >= this.count) return undefined;
    return this.buffer[(this.tail + index) % this.size];
  }

  // Restituisce l'elemento più vecchio
  peek() {
    if (this.count === 0) return undefined;
    return this.buffer[this.tail];
  }

  // Restituisce tutti gli elementi come array
  toArray() {
    const result = [];
    for (let i = 0; i < this.count; i++) {
      result.push(this.get(i));
    }
    return result;
  }
  // Nuovo metodo O(1)
  isFull() {
    return this.count === this.size;
  }
  
  areAllElementsNonEmpty() {
    if (!this.isFull()) return false;
    
    for (let i = 0; i < this.count; i++) {
      const element = this.get(i);
      if (!element || element.length === 0) {
        return false;
      }
    }
    return true;
  }
  
  // Iterazione sugli elementi
  *[Symbol.iterator]() {
    for (let i = 0; i < this.count; i++) {
      yield this.get(i);
    }
  }
}



let replayTime = 1312; // 1130 * 16 = 18 - 3sec = 15
//let blobBuffer = Array(16).fill().map(() => new Blob()); // Aumentato a 64 segmenti
let bblen = 16;
let blobBuffer = new CircularBuffer(bblen);

let saveBuffer;
let recorder;
let codecs = "video/webm;codecs=vp9";
let vbps = 3500000;



// Nel JavaScript
document.getElementById('startCapture').addEventListener('click', async () => {
  try {
    navigator.mediaDevices.getDisplayMedia({
      video: {
        cursor: "always"
      },
      audio: false
    }).then(mediaStream => {
		        startRecording(mediaStream);

		});
    
    //const videoElement = document.querySelector('video');
    //videoElement.srcObject = stream;


  } catch (error) {
    console.error('Errore:', error);
  }
});



function startRecording(stream) {
    try {
        recorder = new MediaRecorder(stream, {
            type: codecs,
            videoBitsPerSecond: vbps
        });

        recorder.addEventListener("dataavailable", (evt) => {
           // try {
                //blobBuffer.shift();
                //blobBuffer.push(evt.data);
				//blobBuffer = blobBuffer.slice(1);
				//blobBuffer.push(evt.data);                
				blobBuffer.push(evt.data); // 18/12/2024 - new CircularBuffer
				
				
                //show_buffer();
            //} catch (error) {
            //    console.error('Errore gestione buffer:', error);
            //}
        });

        recorder.addEventListener("error", (error) => {
            console.error('Errore MediaRecorder:', error);
            // Tentativo di recupero
            setTimeout(() => {
                if (recorder.state === "inactive") {
                    recordLoop();
                }
            }, 500);
        });

        recordLoop();
    } catch (error) {
        console.error('Errore avvio registrazione:', error);
    }
}




function recordLoop() {
		//	show_buffer();
	
    if (recorder.state === "inactive") {
        recorder.start();
    }

    requestAnimationFrame(() => {
        setTimeout(() => {
            if (recorder.state === "recording") {
                recorder.stop();
                recorder.start();
            }
            recordLoop();
        }, replayTime);
    });
}





function salvaReplay()
{
			let tipologia ="Desk-Top";
			saveReplay = false;
			file_totali = bblen;
//			document.getElementById("salva_replay_goal").disabled = true;
			
			let nomeFile = new Date().toLocaleString("it-IT", {timeZone:"Europe/Rome"}).replaceAll(/[:, /]/g, "_");
			let nF = nomeFile;
			
			saveBuffer = blobBuffer.toArray();
			//console.log(saveBuffer.length);
			for (i=0;i<saveBuffer.length;i++)
				{
//					file_da_caricare++;
//					coda_file();
					//setTimeout(upload,(10 + (i*20)),saveBuffer[i],"part"+i+".webm",nomeFile.toLocaleString("it-IT",{timeZone:"Europe/Rome"}).replaceAll(":","_").replaceAll(",","_").replaceAll("/","_").replaceAll(" ","")+tipologia);
					setTimeout(upload,(i*20),saveBuffer[i],"part"+i+".webm",nF+tipologia);
					//localStorage.setItem("part"+i+".webm", URL.createObjectURL(blobBuffer[i]));					
				}

			/*for (i=0;i<blobBuffer.length;i++)
				{
					fetch(localStorage.getItem("part"+i+".webm")).then(r => {
						let blob = r.blob();
						console.log(blob);
						
						upload(blob,tipologia + nomeFile.toLocaleString("it-IT",{timeZone:"Europe/Rome"}).replaceAll(":","_").replaceAll(",","_").replaceAll("/","_").replaceAll(" ",""));	
						});
					
				}*/
				 
				   let formData = new FormData();
					//formData.append('minuto', Math.random() * 90);
					formData.append('folder', nF + tipologia);
					//formData.append('partita', event);
					//formData.append('from', from_unique);
            
            
				 fetch("/jobs.php", {
					method: 'POST',
					body: formData
				});

			
			//addVideo(videoURL);
			
		

}




function upload(blob, fileName, folder) {
    return new Promise((resolve, reject) => {
        let serverUrl = '../upload/save.php';
        let formData = new FormData();
        formData.append('video-filename', fileName);
        formData.append('folder', folder);
        formData.append('video-blob', blob);

        fetch(serverUrl, {
            method: 'POST',
            body: formData
        }).then(response => {
            if (!response.ok) {
                throw new Error('Errore nella risposta del server');
            }
            file_da_caricare--;
            coda_file();
            resolve();
        }).catch(error => {
            file_da_caricare--;
            file_errore++;
            coda_file();
            reject(error);
        });
    });
}

function save_Replay()
{
		salvaReplay();
}
